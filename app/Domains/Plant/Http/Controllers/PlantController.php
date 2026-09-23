<?php

declare(strict_types=1);

namespace App\Domains\Plant\Http\Controllers;

use App\Domains\Plant\Models\PlantEvent;
use App\Domains\Plant\Models\PlantItem;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlantController
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        return Inertia::render('plant/index', [
            'items' => PlantItem::query()->with(['project:id,name', 'supplier:id,name', 'events' => fn ($q) => $q->with('project:id,name')->limit(5)])
                ->orderBy('asset_number')->get()
                ->map(static fn (PlantItem $p): array => [
                    'id' => $p->ulid, 'asset' => $p->asset_number, 'description' => $p->description, 'category' => $p->category,
                    'makeModel' => $p->make_model, 'ownership' => $p->ownership, 'supplier' => $p->supplier?->name,
                    'rate' => $p->hire_rate_per_day === null ? null : (float) $p->hire_rate_per_day, 'project' => $p->project?->name,
                    'status' => $p->status, 'nextService' => $p->next_service_on?->toDateString(),
                    'serviceDue' => $p->next_service_on !== null && $p->next_service_on->lessThanOrEqualTo(Carbon::today()->addDays(7)),
                    'events' => $p->events->map(static fn (PlantEvent $e): array => ['type' => $e->type, 'on' => $e->happened_on->toDateString(), 'project' => $e->project?->name, 'notes' => $e->notes])->values(),
                ]),
            'canManage' => $request->user()?->can('manage-plant') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-plant');
        $data = $request->validate([
            'asset_number' => ['required', 'string', 'max:30', Rule::unique('plant_items')->where('company_id', $this->context->id())],
            'description' => ['required', 'string', 'max:200'], 'category' => ['required', 'string', 'max:30'],
            'make_model' => ['nullable', 'string', 'max:120'], 'serial_number' => ['nullable', 'string', 'max:60'],
            'ownership' => ['required', 'in:owned,hired'],
            'supplier' => ['nullable', 'required_if:ownership,hired', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'hire_rate_per_day' => ['nullable', 'numeric', 'min:0'],
            'service_interval_days' => ['nullable', 'integer', 'between:1,730'], 'next_service_on' => ['nullable', 'date'],
        ]);

        $supplier = $data['supplier'] ?? null;
        unset($data['supplier']);
        PlantItem::query()->create([...$data, 'supplier_id' => $supplier ? Supplier::query()->where('ulid', $supplier)->value('id') : null]);

        return back()->with('success', "{$data['asset_number']} added to the plant register.");
    }

    /**
     * Move to a site, record a service or breakdown, or off-hire.
     */
    public function event(Request $request, PlantItem $item): RedirectResponse
    {
        Gate::authorize('manage-plant');
        $data = $request->validate([
            'type' => ['required', 'in:moved,serviced,breakdown,repaired,off_hired'],
            'project' => ['nullable', 'required_if:type,moved', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'happened_on' => ['required', 'date', 'before_or_equal:today'], 'cost' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $projectId = isset($data['project']) ? Project::query()->where('ulid', $data['project'])->value('id') : null;
        $on = Carbon::parse((string) $data['happened_on']);

        DB::transaction(function () use ($item, $data, $user, $projectId, $on): void {
            PlantEvent::query()->create([
                'plant_item_id' => $item->id, 'type' => $data['type'], 'project_id' => $projectId ?? $item->project_id,
                'happened_on' => $on->toDateString(), 'cost' => $data['cost'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id,
            ]);

            match ($data['type']) {
                'moved' => $item->update(['project_id' => $projectId, 'status' => 'on_site']),
                'serviced' => $item->update(['status' => $item->project_id ? 'on_site' : 'available', 'next_service_on' => $item->service_interval_days ? $on->copy()->addDays($item->service_interval_days)->toDateString() : null]),
                'breakdown' => $item->update(['status' => 'broken']),
                'repaired' => $item->update(['status' => $item->project_id ? 'on_site' : 'available']),
                default => $item->update(['status' => 'off_hired', 'project_id' => null]), // off_hired (validated above)
            };
        });

        return back()->with('success', "{$item->asset_number} updated.");
    }
}
