<?php

declare(strict_types=1);

namespace App\Domains\Land\Http\Controllers;

use App\Domains\Land\Enums\CheckResult;
use App\Domains\Land\Enums\LandStatus;
use App\Domains\Land\Models\LandCheck;
use App\Domains\Land\Models\LandParcel;
use App\Domains\Land\Services\LandService;
use App\Domains\Platform\Enums\Province;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LandController
{
    public function __construct(private readonly LandService $land, private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        $status = LandStatus::tryFrom($request->string('status')->toString());

        $parcels = LandParcel::query()
            ->with('project:id,ulid,name')
            ->withCount([
                'checks as open_checks' => fn ($q) => $q->where('is_required', true)->where('result', CheckResult::Pending),
                'checks as issues' => fn ($q) => $q->where('result', CheckResult::Issue),
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return Inertia::render('land/index', [
            'parcels' => $parcels->map(static fn (LandParcel $p): array => [
                'id' => $p->ulid,
                'name' => $p->name,
                'description' => $p->property_description,
                'location' => trim(implode(', ', array_filter([$p->town, $p->province?->label()]))),
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'price' => $p->offer_price ?? $p->asking_price,
                'openChecks' => (int) $p->getAttribute('open_checks'),
                'issues' => (int) $p->getAttribute('issues'),
                'project' => $p->project?->name,
            ]),
            'statuses' => array_map(static fn (LandStatus $s): array => ['key' => $s->value, 'label' => $s->label()], LandStatus::cases()),
            'provinces' => array_map(static fn (Province $p): array => ['key' => $p->value, 'label' => $p->label()], Province::cases()),
            'filter' => $status?->value,
            'canManage' => $request->user()?->can('manage-land') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-land');
        $parcel = $this->land->create($this->validated($request));

        return redirect()->route('land.show', $parcel)->with('success', 'Land added. Work through the due-diligence checks before making an offer.');
    }

    public function show(Request $request, LandParcel $parcel): Response
    {
        $parcel->load(['checks.checker', 'project:id,ulid,name']);

        return Inertia::render('land/show', [
            'parcel' => [
                'id' => $parcel->ulid,
                'name' => $parcel->name,
                'property_description' => $parcel->property_description,
                'title_deed_number' => $parcel->title_deed_number,
                'province' => $parcel->province?->value,
                'town' => $parcel->town,
                'size_m2' => $parcel->size_m2,
                'current_zoning' => $parcel->current_zoning,
                'seller_name' => $parcel->seller_name,
                'asking_price' => $parcel->asking_price,
                'offer_price' => $parcel->offer_price,
                'status' => $parcel->status->value,
                'offer_date' => $parcel->offer_date?->toDateString(),
                'acceptance_date' => $parcel->acceptance_date?->toDateString(),
                'transfer_date' => $parcel->transfer_date?->toDateString(),
                'notes' => $parcel->notes,
                'project' => $parcel->project ? ['id' => $parcel->project->ulid, 'name' => $parcel->project->name] : null,
            ],
            'checks' => $parcel->checks->map(static fn (LandCheck $c): array => [
                'id' => $c->id, 'title' => $c->title, 'required' => $c->is_required, 'result' => $c->result->value,
                'notes' => $c->notes, 'checkedBy' => $c->checker?->name, 'checkedAt' => $c->checked_at?->toIso8601String(),
            ]),
            'outstanding' => $this->land->outstanding($parcel),
            'statuses' => array_map(static fn (LandStatus $s): array => ['key' => $s->value, 'label' => $s->label()], LandStatus::cases()),
            'provinces' => array_map(static fn (Province $p): array => ['key' => $p->value, 'label' => $p->label()], Province::cases()),
            'canManage' => $request->user()?->can('manage-land') ?? false,
        ]);
    }

    public function update(Request $request, LandParcel $parcel): RedirectResponse
    {
        Gate::authorize('manage-land');
        $parcel->update($this->validated($request));

        return back()->with('success', 'Land details saved.');
    }

    public function status(Request $request, LandParcel $parcel): RedirectResponse
    {
        Gate::authorize('manage-land');
        $data = $request->validate(['status' => ['required', Rule::enum(LandStatus::class)]]);
        $this->land->changeStatus($parcel, LandStatus::from((string) $data['status']));

        return back()->with('success', 'Status updated to '.LandStatus::from((string) $data['status'])->label().'.');
    }

    public function check(Request $request, LandParcel $parcel, LandCheck $check): RedirectResponse
    {
        Gate::authorize('manage-land');
        abort_unless($check->land_parcel_id === $parcel->id, 404);

        $data = $request->validate([
            'result' => ['required', Rule::enum(CheckResult::class)],
            'notes' => ['nullable', 'required_if:result,issue', 'string', 'max:2000'],
        ], ['notes.required_if' => 'Describe the issue so others can see what needs resolving.']);

        /** @var User $user */
        $user = $request->user();
        $this->land->recordCheck($check, CheckResult::from((string) $data['result']), isset($data['notes']) ? (string) $data['notes'] : null, $user);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'property_description' => ['nullable', 'string', 'max:255'],
            'title_deed_number' => ['nullable', 'string', 'max:40'],
            'province' => ['nullable', Rule::enum(Province::class)],
            'town' => ['nullable', 'string', 'max:120'],
            'size_m2' => ['nullable', 'numeric', 'min:1'],
            'current_zoning' => ['nullable', 'string', 'max:120'],
            'seller_name' => ['nullable', 'string', 'max:160'],
            'asking_price' => ['nullable', 'numeric', 'min:0'],
            'offer_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
        ]);

        $projectUlid = $data['project'] ?? null;
        unset($data['project']);
        $data['project_id'] = is_string($projectUlid) ? Project::query()->where('ulid', $projectUlid)->value('id') : null;

        return $data;
    }
}
