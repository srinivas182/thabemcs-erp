<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Http\Controllers;

use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\LeaseInspection;
use App\Domains\Rentals\Models\LeaseInvoice;
use App\Domains\Rentals\Models\LeaseReceipt;
use App\Domains\Rentals\Models\MaintenanceRequest;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Rentals\Services\LeaseService;
use App\Domains\Rentals\Services\RentalException;
use App\Domains\Rentals\Services\RentBillingService;
use App\Domains\Sales\Models\SaleUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Leases: the register, one lease's detail, billing, receipts and the deposit.
 */
final class RentalController
{
    public function __construct(private readonly LeaseService $leases, private readonly RentBillingService $billing) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-rentals');
        $status = $request->string('status')->toString() ?: 'active';

        $leases = Lease::query()->with(['unit.project:id,code', 'tenant:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('number')->paginate(25)->withQueryString();

        $vacant = SaleUnit::query()->whereIn('tenure', ['rental', 'both'])->whereNotIn('status', ['sold', 'transferred', 'withdrawn'])
            ->whereDoesntHave('leases', fn ($q) => $q->where('status', 'active'))->count();

        return Inertia::render('rentals/index', [
            'leases' => $leases->through(fn (Lease $l): array => [
                'id' => $l->ulid, 'reference' => $l->reference(), 'unit' => $l->unit->reference, 'project' => $l->unit->project->code,
                'tenant' => $l->tenant->name, 'type' => $l->type, 'starts' => $l->starts_on->toDateString(),
                'ends' => $l->ends_on?->toDateString(), 'monthToMonth' => $l->month_to_month,
                'rent' => $l->rentAt(Carbon::today()), 'status' => $l->status, 'arrears' => $this->leases->arrears($l)['total'],
            ]),
            'filters' => ['status' => $status],
            'summary' => [
                'active' => Lease::query()->where('status', 'active')->count(),
                'vacant' => $vacant,
                'monthlyRent' => round((float) Lease::query()->where('status', 'active')->get()->sum(static fn (Lease $l): float => $l->rentAt(Carbon::today())), 2),
            ],
            'canManage' => Gate::allows('manage-rentals'),
        ]);
    }

    public function show(Lease $lease): Response
    {
        Gate::authorize('view-rentals');
        $lease->load(['unit.project:id,ulid,code', 'tenant', 'charges']);
        $today = Carbon::today();

        return Inertia::render('rentals/lease', [
            'lease' => [
                'id' => $lease->ulid, 'reference' => $lease->reference(), 'status' => $lease->status, 'type' => $lease->type,
                'unit' => $lease->unit->reference, 'project' => ['id' => $lease->unit->project->ulid, 'code' => $lease->unit->project->code],
                'tenant' => ['id' => $lease->tenant->ulid, 'name' => $lease->tenant->name, 'email' => $lease->tenant->email, 'phone' => $lease->tenant->phone,
                    'fica' => $lease->tenant->fica_verified, 'credit' => $lease->tenant->credit_checked],
                'signed' => $lease->signed_on?->toDateString(), 'starts' => $lease->starts_on->toDateString(), 'ends' => $lease->ends_on?->toDateString(),
                'monthToMonth' => $lease->month_to_month, 'rent' => (float) $lease->rent_amount, 'rentNow' => $lease->rentAt($today),
                'escalation' => (float) $lease->escalation_percent, 'vat' => $lease->vat_applies, 'paymentDay' => $lease->payment_day,
                'noticeDays' => $lease->notice_days, 'deposit' => (float) $lease->deposit_amount, 'depositAccount' => $lease->deposit_account,
                'depositReceived' => $lease->deposit_received_on?->toDateString(), 'depositInterest' => (float) $lease->deposit_interest,
                'depositDeductions' => (float) $lease->deposit_deductions, 'depositRefundDue' => $this->leases->depositRefundDue($lease),
                'depositRefunded' => $lease->deposit_refunded_on?->toDateString(), 'ended' => $lease->ended_on?->toDateString(),
                'endReason' => $lease->end_reason, 'notes' => $lease->notes,
            ],
            'charges' => $lease->charges->map(static fn ($c): array => ['id' => $c->id, 'type' => $c->type, 'description' => $c->description,
                'amount' => (float) $c->amount, 'escalates' => $c->escalates, 'vat' => $c->vat_applies])->values(),
            'invoices' => LeaseInvoice::query()->where('lease_id', $lease->id)->orderByDesc('period_start')->limit(24)->get()
                ->map(static fn (LeaseInvoice $i): array => ['id' => $i->ulid, 'reference' => $i->reference(), 'period' => $i->period_start->format('M Y'),
                    'due' => $i->due_on->toDateString(), 'total' => (float) $i->total, 'paid' => (float) $i->paid, 'outstanding' => $i->outstanding(),
                    'status' => $i->status, 'lines' => $i->lines])->values(),
            'receipts' => LeaseReceipt::query()->where('lease_id', $lease->id)->orderByDesc('received_on')->limit(24)->get()
                ->map(static fn (LeaseReceipt $r): array => ['id' => $r->id, 'amount' => (float) $r->amount, 'on' => $r->received_on->toDateString(),
                    'method' => $r->method, 'reference' => $r->reference])->values(),
            'arrears' => $this->leases->arrears($lease),
            'inspections' => LeaseInspection::query()->where('lease_id', $lease->id)->orderByDesc('inspected_on')->get()
                ->map(static fn (LeaseInspection $i): array => ['id' => $i->ulid, 'type' => $i->type, 'on' => $i->inspected_on->toDateString(),
                    'tenantPresent' => $i->tenant_present, 'items' => $i->items, 'notes' => $i->notes])->values(),
            'maintenance' => MaintenanceRequest::query()->with('supplier:id,name')->where('lease_id', $lease->id)->orderByDesc('reported_on')->get()
                ->map(static fn (MaintenanceRequest $m): array => ['id' => $m->ulid, 'reference' => $m->reference(), 'category' => $m->category,
                    'description' => $m->description, 'priority' => $m->priority, 'status' => $m->status, 'supplier' => $m->supplier?->name,
                    'cost' => $m->cost === null ? null : (float) $m->cost, 'reported' => $m->reported_on->toDateString(), 'byTenant' => $m->reported_by_tenant])->values(),
            'chargeTypes' => collect((array) config('rentals.charge_types'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'inspectionAreas' => config('rentals.inspection_areas'),
            'conditions' => config('rentals.conditions'),
            'canManage' => Gate::allows('manage-rentals'),
        ]);
    }

    public function store(Request $request, SaleUnit $unit): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'tenant' => ['required', 'string', Rule::exists('tenants', 'ulid')],
            'type' => ['required', 'in:residential,commercial'],
            'signed_on' => ['nullable', 'date'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'month_to_month' => ['boolean'],
            'rent_amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'vat_applies' => ['boolean'],
            'escalation_percent' => ['nullable', 'numeric', 'between:0,50'],
            'payment_day' => ['required', 'integer', 'between:1,28'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_account' => ['nullable', 'string', 'max:190'],
            'deposit_received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $tenant = Tenant::query()->where('ulid', $data['tenant'])->firstOrFail();

        try {
            ['lease' => $lease, 'token' => $token] = $this->leases->create($unit, $tenant, Arr::except($data, ['tenant']), $user);
        } catch (RentalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rentals.lease', $lease)
            ->with('success', "Lease {$lease->reference()} created as a draft. The tenant's link is ".route('tenant.portal', $token).' — send it to them; it is not shown again.');
    }

    public function activate(Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');

        try {
            $this->leases->activate($lease);
        } catch (RentalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Lease activated. Rent is invoiced with the monthly run.');
    }

    public function bill(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate(['month' => ['required', 'date']]);
        $invoice = $this->billing->invoice($lease, Carbon::parse((string) $data['month']));

        return back()->with($invoice ? 'success' : 'error', $invoice
            ? "Invoice {$invoice->reference()} raised for ".$invoice->period_start->format('F Y').'.'
            : 'That month is already invoiced for this lease.');
    }

    public function addCharge(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys((array) config('rentals.charge_types')))],
            'description' => ['required', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'escalates' => ['boolean'],
        ]);
        $this->billing->addCharge($lease, (string) $data['type'], (string) $data['description'], (float) $data['amount'], (bool) ($data['escalates'] ?? false));

        return back()->with('success', 'Charge added. It appears on the next invoice.');
    }

    public function receipt(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'received_on' => ['required', 'date'],
            'method' => ['required', 'in:eft,debit_order,cash,card'],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->leases->receipt($lease, (float) $data['amount'], Carbon::parse((string) $data['received_on']), (string) $data['method'], $data['reference'] ?? null, null, $user);

        return back()->with('success', 'Receipt recorded and allocated to the oldest unpaid invoices.');
    }

    public function inspect(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'type' => ['required', 'in:incoming,outgoing'],
            'inspected_on' => ['required', 'date'],
            'tenant_present' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.area' => ['required', 'string', 'max:60'],
            'items.*.condition' => ['required', Rule::in((array) config('rentals.conditions'))],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $user */
        $user = $request->user();
        LeaseInspection::query()->create([...$data, 'lease_id' => $lease->id, 'conducted_by' => $user->id]);

        return back()->with('success', ucfirst((string) $data['type']).' inspection recorded.');
    }

    public function end(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'ended_on' => ['required', 'date'],
            'end_reason' => ['required', 'string', 'max:190'],
            'deposit_deductions' => ['nullable', 'numeric', 'min:0'],
            'deposit_refunded_on' => ['nullable', 'date'],
        ]);
        $this->leases->end($lease, Carbon::parse((string) $data['ended_on']), (string) $data['end_reason'],
            (float) ($data['deposit_deductions'] ?? 0), isset($data['deposit_refunded_on']) ? Carbon::parse((string) $data['deposit_refunded_on']) : null);

        $lease->refresh();

        return back()->with('success', 'Lease ended. The deposit refund due is R'.number_format($this->leases->depositRefundDue($lease), 2, '.', ' ').'.');
    }
}
