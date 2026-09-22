<?php

declare(strict_types=1);

namespace App\Domains\Site\Http\Controllers\Api;

use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Enums\IncidentType;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Enums\Weather;
use App\Domains\Site\Models\Delivery;
use App\Domains\Site\Services\SiteCaptureService;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Endpoints the offline site app syncs to. Field names match packages/shared (camelCase).
 * Each create returns 201 for a new record and 200 when the client_id was already received.
 */
final class SiteApiController
{
    public function __construct(private readonly SiteCaptureService $capture) {}

    public function projects(): JsonResponse
    {
        Gate::authorize('capture-site');

        $projects = Project::query()->where('status', ProjectStatus::Active)->orderBy('name')
            ->get(['id', 'ulid', 'code', 'name', 'town', 'latitude', 'longitude', 'geofence_radius_m']);

        return response()->json(['data' => $projects->map(static fn (Project $p): array => [
            'id' => $p->ulid, 'code' => $p->code, 'name' => $p->name, 'town' => $p->town,
            'latitude' => $p->getAttribute('latitude') === null ? null : (float) $p->getAttribute('latitude'),
            'longitude' => $p->getAttribute('longitude') === null ? null : (float) $p->getAttribute('longitude'),
            'geofenceRadius' => (int) $p->getAttribute('geofence_radius_m'),
        ])]);
    }

    public function suppliers(): JsonResponse
    {
        Gate::authorize('capture-site');

        return response()->json(['data' => Supplier::query()->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])
            ->map(static fn (Supplier $s): array => ['id' => $s->ulid, 'name' => $s->name])]);
    }

    public function diary(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'weather' => ['required', Rule::enum(Weather::class)],
            'workersOnSite' => ['required', 'integer', 'between:0,5000'],
            'workCompleted' => ['required', 'string', 'min:3', 'max:4000'],
            'delays' => ['nullable', 'string', 'max:2000'],
            'capturedAt' => ['required', 'date'],
        ]);

        [$diary, $created] = $this->capture->diary($project, [
            'client_id' => $data['clientId'], 'diary_date' => $data['date'], 'weather' => $data['weather'],
            'workers_on_site' => (int) $data['workersOnSite'], 'work_completed' => $data['workCompleted'],
            'delays' => $data['delays'] ?? null, 'captured_at' => $data['capturedAt'],
        ], $this->user($request));

        return response()->json(['data' => ['id' => $diary->id]], $created ? 201 : 200);
    }

    public function attendance(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'direction' => ['required', 'in:in,out'],
            'capturedAt' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'selfie' => ['nullable', 'image', 'max:5120'],
        ]);

        return $this->guardStorage(function () use ($project, $data, $request): JsonResponse {
            [$row, $created] = $this->capture->attendance($project, [
                'client_id' => $data['clientId'], 'direction' => $data['direction'], 'captured_at' => $data['capturedAt'],
                'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                'accuracy_m' => isset($data['accuracy']) ? (int) $data['accuracy'] : null,
            ], $this->user($request), $request->file('selfie'));

            return response()->json(['data' => ['id' => $row->id, 'withinGeofence' => $row->within_geofence, 'distance' => $row->distance_m]], $created ? 201 : 200);
        });
    }

    public function photo(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'capturedAt' => ['required', 'date'],
            'caption' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'file' => ['required', 'image', 'max:10240'],
        ]);

        return $this->guardStorage(function () use ($project, $data, $request): JsonResponse {
            [$photo, $created] = $this->capture->photo($project, [
                'client_id' => $data['clientId'], 'captured_at' => $data['capturedAt'], 'caption' => $data['caption'] ?? null,
                'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            ], $request->file('file'), $this->user($request), $this->attachable($request));

            return response()->json(['data' => ['id' => $photo->id]], $created ? 201 : 200);
        });
    }

    public function delivery(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'supplierId' => ['nullable', 'string'],
            'supplierName' => ['nullable', 'required_without:supplierId', 'string', 'max:160'],
            'deliveryNote' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'string', 'max:4000'],
            'condition' => ['required', 'in:good,damaged,short'],
            'notes' => ['nullable', 'required_unless:condition,good', 'string', 'max:2000'],
            'receivedAt' => ['required', 'date'],
        ], ['notes.required_unless' => 'Describe what was damaged or short.']);

        $supplier = isset($data['supplierId']) ? Supplier::query()->where('ulid', $data['supplierId'])->first() : null;

        [$delivery, $created] = $this->capture->delivery($project, [
            'client_id' => $data['clientId'], 'supplier_id' => $supplier?->id, 'supplier_name' => $supplier !== null ? $supplier->name : ($data['supplierName'] ?? null),
            'delivery_note_number' => $data['deliveryNote'] ?? null, 'items' => $data['items'], 'condition' => $data['condition'],
            'notes' => $data['notes'] ?? null, 'received_at' => $data['receivedAt'],
        ], $this->user($request));

        return response()->json(['data' => ['id' => $delivery->ulid]], $created ? 201 : 200);
    }

    public function incident(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(IncidentType::class)],
            'occurredAt' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:5', 'max:4000'],
            'immediateAction' => ['nullable', 'string', 'max:2000'],
            'personInvolved' => ['nullable', 'string', 'max:160'],
        ]);

        [$incident, $created] = $this->capture->incident($project, [
            'client_id' => $data['clientId'], 'type' => $data['type'], 'occurred_at' => $data['occurredAt'],
            'location' => $data['location'] ?? null, 'description' => $data['description'],
            'immediate_action' => $data['immediateAction'] ?? null, 'person_involved' => $data['personInvolved'] ?? null,
        ], $this->user($request));

        return response()->json(['data' => ['id' => $incident->ulid, 'reportable' => $incident->reportable]], $created ? 201 : 200);
    }

    private function project(Request $request): Project
    {
        Gate::authorize('capture-site');
        $request->validate(['projectId' => ['required', 'string']]);

        // Company scoping means another company's project simply is not found.
        return Project::query()->where('ulid', $request->string('projectId')->toString())->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function attachable(Request $request): ?Model
    {
        $type = $request->string('attachTo')->toString();
        $id = $request->string('attachId')->toString();

        return match ($type) {
            'delivery' => Delivery::query()->where('ulid', $id)->first(),
            'incident' => SafetyIncident::query()->where('ulid', $id)->first(),
            default => null,
        };
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    private function guardStorage(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (QuotaExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 507);
        }
    }
}
