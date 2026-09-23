<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Http\Controllers;

use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\MaintenanceRequest;
use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance requests, raised by the letting team or by a tenant through their own link.
 */
final class MaintenanceController
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-rentals');
        $status = $request->string('status')->toString() ?: 'open';

        return Inertia::render('rentals/maintenance', [
            'requests' => MaintenanceRequest::query()->with(['unit.project:id,code', 'supplier:id,name', 'lease.tenant:id,name'])
                ->when($status === 'open', fn ($q) => $q->whereIn('status', ['new', 'assigned', 'in_progress']))
                ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
                ->orderByRaw("case priority when 'urgent' then 0 when 'normal' then 1 else 2 end")->orderBy('reported_on')
                ->paginate(30)->withQueryString()
                ->through(static fn (MaintenanceRequest $m): array => [
                    'id' => $m->ulid, 'reference' => $m->reference(), 'unit' => $m->unit->reference, 'project' => $m->unit->project->code,
                    'tenant' => $m->lease?->tenant->name, 'category' => $m->category, 'description' => $m->description,
                    'priority' => $m->priority, 'status' => $m->status, 'supplier' => $m->supplier?->name,
                    'cost' => $m->cost === null ? null : (float) $m->cost, 'recover' => $m->recover_from_tenant,
                    'reported' => $m->reported_on->toDateString(), 'completed' => $m->completed_on?->toDateString(),
                    'byTenant' => $m->reported_by_tenant, 'resolution' => $m->resolution,
                ]),
            'filters' => ['status' => $status],
            'categories' => collect((array) config('rentals.maintenance_categories'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'canManage' => Gate::allows('manage-rentals'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'unit' => ['required', 'string', Rule::exists('sale_units', 'ulid')],
            'category' => ['required', Rule::in(array_keys((array) config('rentals.maintenance_categories')))],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:urgent,normal,low'],
        ]);
        $unit = SaleUnit::query()->where('ulid', $data['unit'])->firstOrFail();
        $this->raise($unit, (string) $data['category'], (string) $data['description'], (string) $data['priority'], false);

        return back()->with('success', 'Maintenance request logged.');
    }

    public function update(Request $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'status' => ['required', 'in:new,assigned,in_progress,completed,cancelled'],
            'supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'recover_from_tenant' => ['boolean'],
            'resolution' => ['nullable', 'required_if:status,completed', 'string', 'max:2000'],
        ], ['resolution.required_if' => 'Record what was done.']);

        $maintenance->update([
            'status' => $data['status'],
            'supplier_id' => isset($data['supplier']) ? Supplier::query()->where('ulid', $data['supplier'])->value('id') : $maintenance->supplier_id,
            'cost' => $data['cost'] ?? $maintenance->cost,
            'recover_from_tenant' => (bool) ($data['recover_from_tenant'] ?? $maintenance->recover_from_tenant),
            'resolution' => $data['resolution'] ?? $maintenance->resolution,
            'completed_on' => $data['status'] === 'completed' ? now()->toDateString() : null,
        ]);

        return back()->with('success', 'Maintenance request updated.');
    }

    /** The tenant's own page, reached from the link in their lease. No sign-in. */
    public function portal(string $token): Response
    {
        $lease = $this->leaseFor($token);

        return $this->context->runFor($this->companyOf($lease), function () use ($lease, $token): Response {
            $lease->load(['unit.project:id,name', 'tenant:id,name']);

            return Inertia::render('rentals/tenant-portal', [
                'token' => $token,
                'lease' => [
                    'reference' => $lease->reference(), 'unit' => $lease->unit->reference, 'project' => $lease->unit->project->name,
                    'tenant' => $lease->tenant->name, 'rent' => $lease->rentAt(now()), 'paymentDay' => $lease->payment_day,
                    'starts' => $lease->starts_on->toDateString(), 'ends' => $lease->ends_on?->toDateString(), 'status' => $lease->status,
                ],
                'invoices' => $lease->invoices()->limit(12)->get()->map(static fn ($i): array => [
                    'reference' => $i->reference(), 'period' => $i->period_start->format('M Y'), 'due' => $i->due_on->toDateString(),
                    'total' => (float) $i->total, 'outstanding' => $i->outstanding(), 'status' => $i->status,
                ])->values(),
                'requests' => MaintenanceRequest::query()->where('lease_id', $lease->id)->orderByDesc('reported_on')->limit(20)->get()
                    ->map(static fn (MaintenanceRequest $m): array => ['reference' => $m->reference(), 'description' => $m->description,
                        'status' => $m->status, 'reported' => $m->reported_on->toDateString()])->values(),
                'categories' => collect((array) config('rentals.maintenance_categories'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            ]);
        });
    }

    public function portalRequest(Request $request, string $token): RedirectResponse
    {
        $lease = $this->leaseFor($token);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys((array) config('rentals.maintenance_categories')))],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:urgent,normal,low'],
        ]);

        $this->context->runFor($this->companyOf($lease), function () use ($lease, $data): void {
            $this->raise($lease->unit, (string) $data['category'], (string) $data['description'], (string) $data['priority'], true, $lease);
        });

        return back()->with('success', 'Thank you. Your request has been logged and the letting team has been told.');
    }

    private function companyOf(Lease $lease): Company
    {
        return Company::query()->whereKey((int) $lease->getAttribute('company_id'))->firstOrFail();
    }

    private function leaseFor(string $token): Lease
    {
        $lease = Lease::query()->withoutGlobalScope(CompanyScope::class)->where('token_hash', hash('sha256', $token))
            ->whereIn('status', ['active', 'draft'])->first();
        abort_if($lease === null, 404);

        return $lease;
    }

    private function raise(SaleUnit $unit, string $category, string $description, string $priority, bool $byTenant, ?Lease $lease = null): MaintenanceRequest
    {
        $lease ??= Lease::query()->where('sale_unit_id', $unit->id)->where('status', 'active')->first();

        $request = DB::transaction(fn (): MaintenanceRequest => MaintenanceRequest::query()->create([
            'number' => (int) MaintenanceRequest::query()->lockForUpdate()->max('number') + 1,
            'sale_unit_id' => $unit->id, 'lease_id' => $lease?->id, 'category' => $category, 'description' => $description,
            'priority' => $priority, 'status' => 'new', 'reported_by_tenant' => $byTenant, 'reported_on' => now()->toDateString(),
        ]));

        if ($byTenant) {
            User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales-leasing', 'company-admin']))->get()
                ->each(fn (User $person) => $person->notify(new SystemMessage(
                    ($priority === 'urgent' ? 'Urgent ' : '')."maintenance request: {$unit->reference}",
                    mb_substr($description, 0, 200),
                    route('rentals.maintenance'),
                )));
        }

        return $request;
    }
}
