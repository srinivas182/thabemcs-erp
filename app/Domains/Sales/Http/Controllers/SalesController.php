<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\Buyer;
use App\Domains\Sales\Models\Reservation;
use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Sales\Services\SalesException;
use App\Domains\Sales\Services\SalesRevenueService;
use App\Domains\Sales\Services\SalesService;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The stock schedule for a project: what is available, reserved, sold and transferred.
 */
final class SalesController
{
    public function __construct(private readonly SalesService $sales, private readonly SalesRevenueService $revenue) {}

    public function units(Project $project): Response
    {
        Gate::authorize('view-sales');

        $agreements = SaleAgreement::query()->with('buyer:id,name')->whereIn('status', ['conditional', 'unconditional', 'registered'])
            ->whereIn('sale_unit_id', SaleUnit::query()->where('project_id', $project->id)->select('id'))->get()->keyBy('sale_unit_id');
        $reservations = Reservation::query()->with('buyer:id,name')->where('status', 'active')
            ->whereIn('sale_unit_id', SaleUnit::query()->where('project_id', $project->id)->select('id'))->get()->keyBy('sale_unit_id');

        return Inertia::render('sales/units', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'units' => SaleUnit::query()->where('project_id', $project->id)->orderBy('sort')->orderBy('reference')->get()
                ->map(static function (SaleUnit $u) use ($agreements, $reservations): array {
                    $agreement = $agreements->get($u->id);
                    $reservation = $reservations->get($u->id);

                    return [
                        'id' => $u->ulid, 'reference' => $u->reference, 'type' => $u->type, 'description' => $u->description,
                        'size' => $u->size_m2 === null ? null : (float) $u->size_m2, 'bedrooms' => $u->bedrooms,
                        'price' => (float) $u->list_price, 'netPrice' => $u->netPrice(), 'vat' => $u->vat_applies,
                        'nhbrc' => $u->nhbrc_enrolment, 'status' => $u->status,
                        'buyer' => $agreement?->buyer->name ?? $reservation?->buyer->name,
                        'agreement' => $agreement?->ulid, 'agreementRef' => $agreement?->reference(),
                        'reservationExpires' => $reservation?->expires_on->toDateString(),
                    ];
                })->values(),
            'revenue' => $this->revenue->forProject($project),
            'unitTypes' => collect((array) config('sales.unit_types'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'conditionTypes' => collect((array) config('sales.condition_types'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'reservationDays' => (int) config('sales.reservation_days'),
            'commissionPercent' => (float) config('sales.commission_percent'),
            'canManage' => Gate::allows('manage-sales'),
        ]);
    }

    public function storeUnit(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40', Rule::unique('sale_units', 'reference')->where('project_id', $project->id)],
            'type' => ['required', Rule::in(array_keys((array) config('sales.unit_types')))],
            'description' => ['nullable', 'string', 'max:190'],
            'size_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'list_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'vat_applies' => ['boolean'],
            'nhbrc_enrolment' => ['nullable', 'string', 'max:40'],
        ]);
        SaleUnit::query()->create([...$data, 'project_id' => $project->id]);

        return back()->with('success', "Unit {$data['reference']} added to the stock schedule.");
    }

    public function updatePrice(Request $request, SaleUnit $unit): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->sales->changePrice($unit, (float) $data['price'], Carbon::parse((string) $data['effective_from']), $data['reason'] ?? null, $user);

        return back()->with('success', 'Price updated.');
    }

    public function withdraw(SaleUnit $unit): RedirectResponse
    {
        Gate::authorize('manage-sales');
        if (! in_array($unit->status, ['available', 'withdrawn'], true)) {
            return back()->with('error', "{$unit->reference} is {$unit->status} and cannot be withdrawn.");
        }
        $unit->update(['status' => $unit->status === 'withdrawn' ? 'available' : 'withdrawn']);

        return back()->with('success', "{$unit->reference} is now {$unit->status}.");
    }

    public function reserve(Request $request, SaleUnit $unit): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'buyer' => ['required', 'string', Rule::exists('buyers', 'ulid')],
            'reserved_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after:reserved_on'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $buyer = Buyer::query()->where('ulid', $data['buyer'])->firstOrFail();

        try {
            $this->sales->reserve($unit, $buyer, Carbon::parse((string) $data['reserved_on']),
                isset($data['expires_on']) ? Carbon::parse((string) $data['expires_on']) : null,
                (float) ($data['deposit_amount'] ?? 0), $data['notes'] ?? null, $user);
        } catch (SalesException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$unit->reference} reserved for {$buyer->name}.");
    }

    public function sign(Request $request, SaleUnit $unit): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'buyer' => ['required', 'string', Rule::exists('buyers', 'ulid')],
            'signed_on' => ['required', 'date', 'before_or_equal:today'],
            'purchase_price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'vat_applies' => ['boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_due_on' => ['nullable', 'date'],
            'deposit_held_by' => ['nullable', 'string', 'max:190'],
            'trust_account_ref' => ['nullable', 'string', 'max:60'],
            'bond_required' => ['boolean'],
            'bond_amount' => ['nullable', 'numeric', 'min:0'],
            'bond_originator' => ['nullable', 'string', 'max:190'],
            'occupation_date' => ['nullable', 'date'],
            'commission_percent' => ['nullable', 'numeric', 'between:0,20'],
            'agent_supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')],
            'conveyancer_supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')],
            'conditions' => ['array', 'max:10'],
            'conditions.*.type' => [Rule::in(array_keys((array) config('sales.condition_types')))],
            'conditions.*.due_on' => ['nullable', 'date'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $buyer = Buyer::query()->where('ulid', $data['buyer'])->firstOrFail();

        $fields = collect($data)->except(['buyer', 'conditions', 'agent_supplier', 'conveyancer_supplier'])->all();
        $fields['agent_supplier_id'] = isset($data['agent_supplier']) ? Supplier::query()->where('ulid', $data['agent_supplier'])->value('id') : null;
        $fields['conveyancer_supplier_id'] = isset($data['conveyancer_supplier']) ? Supplier::query()->where('ulid', $data['conveyancer_supplier'])->value('id') : null;

        try {
            /** @var list<array{type: string, description?: string|null, due_on?: string|null}> $conditions */
            $conditions = array_values($data['conditions'] ?? []);
            $agreement = $this->sales->sign($unit, $buyer, $fields, $conditions, $user);
        } catch (SalesException $e) {
            return back()->with('error', $e->getMessage());
        }

        $warning = $unit->type === 'house' && $unit->nhbrc_enrolment === null ? ' Note: this home has no NHBRC enrolment number recorded.' : '';

        return redirect()->route('sales.agreement', $agreement)->with('success', "Sale {$agreement->reference()} recorded for {$buyer->name}.".$warning);
    }
}
