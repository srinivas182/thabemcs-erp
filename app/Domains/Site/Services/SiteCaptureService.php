<?php

declare(strict_types=1);

namespace App\Domains\Site\Services;

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Platform\Services\QuotaService;
use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Enums\IncidentType;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Models\Delivery;
use App\Domains\Site\Models\SiteAttendance;
use App\Domains\Site\Models\SiteDiary;
use App\Domains\Site\Models\SitePhoto;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Stores records captured on the site app. Every record carries the phone's client_id,
 * so a sync that is retried after a dropped connection returns the existing record instead of a duplicate.
 */
final class SiteCaptureService
{
    public function __construct(
        private readonly Geofence $geofence,
        private readonly QuotaService $quotas,
        private readonly CurrentCompany $context,
    ) {}

    /**
     * One diary per project per day: a later submission for the same day replaces the earlier one.
     *
     * @param  array{client_id: string, diary_date: string, weather: string, workers_on_site: int, work_completed: string, delays?: string|null, captured_at: string}  $data
     * @return array{0: SiteDiary, 1: bool} The diary and whether it was newly created
     */
    public function diary(Project $project, array $data, User $by): array
    {
        if ($existing = SiteDiary::query()->where('client_id', $data['client_id'])->first()) {
            return [$existing, false];
        }

        $diary = SiteDiary::query()->where('project_id', $project->id)->whereDate('diary_date', $data['diary_date'])->first()
            ?? new SiteDiary(['project_id' => $project->id]);

        $created = ! $diary->exists;
        $diary->fill([...$data, 'created_by' => $by->id])->save();

        return [$diary, $created];
    }

    /**
     * @param  array{client_id: string, direction: string, captured_at: string, latitude?: float|null, longitude?: float|null, accuracy_m?: int|null}  $data
     * @return array{0: SiteAttendance, 1: bool}
     */
    public function attendance(Project $project, array $data, User $by, ?UploadedFile $selfie): array
    {
        if ($existing = SiteAttendance::query()->where('client_id', $data['client_id'])->first()) {
            return [$existing, false];
        }

        $distance = null;
        $within = null;
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        $siteLat = $project->getAttribute('latitude');
        $siteLng = $project->getAttribute('longitude');

        if ($lat !== null && $lng !== null && $siteLat !== null && $siteLng !== null) {
            $distance = $this->geofence->distance((float) $lat, (float) $lng, (float) $siteLat, (float) $siteLng);
            // Allow for the phone's GPS accuracy, capped so a poor fix cannot stretch the fence too far.
            $tolerance = min((int) ($data['accuracy_m'] ?? 0), 100);
            $within = $distance <= (int) $project->getAttribute('geofence_radius_m') + $tolerance;
        }

        $attendance = SiteAttendance::query()->create([
            ...$data,
            'project_id' => $project->id,
            'user_id' => $by->id,
            'distance_m' => $distance,
            'within_geofence' => $within,
            'selfie_path' => $selfie ? $this->store($project, $selfie, 'attendance') : null,
        ]);

        return [$attendance, true];
    }

    /**
     * @param  array{client_id: string, captured_at: string, caption?: string|null, latitude?: float|null, longitude?: float|null}  $data
     * @return array{0: SitePhoto, 1: bool}
     */
    public function photo(Project $project, array $data, UploadedFile $file, User $by, ?Model $attachTo = null): array
    {
        if ($existing = SitePhoto::query()->where('client_id', $data['client_id'])->first()) {
            return [$existing, false];
        }

        /** @var string $disk */
        $disk = config('platform.documents_disk');

        $photo = SitePhoto::query()->create([
            ...$data,
            'project_id' => $project->id,
            'attachable_type' => $attachTo?->getMorphClass(),
            'attachable_id' => $attachTo?->getKey(),
            'disk' => $disk,
            'path' => $this->store($project, $file, 'photos'),
            'size_bytes' => (int) $file->getSize(),
            'created_by' => $by->id,
        ]);

        return [$photo, true];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Delivery, 1: bool}
     */
    public function delivery(Project $project, array $data, User $by): array
    {
        if ($existing = Delivery::query()->where('client_id', $data['client_id'])->first()) {
            return [$existing, false];
        }

        $delivery = Delivery::query()->create([...$data, 'project_id' => $project->id, 'received_by' => $by->id]);

        if ($delivery->condition !== 'good') {
            $this->notifyProjectTeam($project, [Role::Procurement, Role::ProjectManager], new SystemMessage(
                'Delivery problem: '.($delivery->supplier_name ?? 'supplier').' ('.$delivery->condition.')',
                "{$project->name}. ".Str::limit($delivery->items, 120),
                route('projects.site', $project).'#deliveries',
                'warning',
            ), $by);
        }

        return [$delivery, true];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: SafetyIncident, 1: bool}
     */
    public function incident(Project $project, array $data, User $by): array
    {
        if ($existing = SafetyIncident::query()->where('client_id', $data['client_id'])->first()) {
            return [$existing, false];
        }

        $type = IncidentType::from((string) $data['type']);
        $incident = SafetyIncident::query()->create([
            ...$data,
            'project_id' => $project->id,
            'reportable' => (bool) ($data['reportable'] ?? $type->defaultReportable()),
            'reported_by' => $by->id,
        ]);

        $serious = $incident->reportable || $type->needsReportabilityReview();
        $this->notifyProjectTeam($project, [Role::SafetyOfficer, Role::ProjectManager, Role::Director], new SystemMessage(
            ($serious ? 'Serious incident: ' : 'Incident reported: ').$type->label(),
            "{$project->name}, ".Carbon::parse($incident->occurred_at)->timezone('Africa/Johannesburg')->format('j M H:i').'. '.Str::limit($incident->description, 140)
                .($incident->reportable ? ' This must be reported to the Department of Employment and Labour.' : ''),
            route('projects.safety', $project),
            $serious ? 'danger' : 'warning',
        ), $by);

        return [$incident, true];
    }

    private function store(Project $project, UploadedFile $file, string $folder): string
    {
        $company = $this->context->require();
        $this->quotas->ensureStorageFor($company, (int) $file->getSize());

        /** @var string $disk */
        $disk = config('platform.documents_disk');

        return (string) $file->storeAs(
            "{$company->ulid}/site/{$project->ulid}/{$folder}",
            Str::random(32).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'),
            $disk,
        );
    }

    /**
     * @param  list<Role>  $roles
     */
    private function notifyProjectTeam(Project $project, array $roles, SystemMessage $message, User $except): void
    {
        setPermissionsTeamId($project->company_id);

        User::query()->where('company_id', $project->company_id)->where('is_active', true)
            ->whereKeyNot($except->id)
            ->role(array_map(static fn (Role $r): string => $r->value, $roles))
            ->get()
            ->each(fn (User $u) => $u->notify($message));
    }
}
