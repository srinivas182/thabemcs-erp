<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleCondition;
use App\Domains\Sales\Models\TransferStep;
use App\Domains\Sales\Services\SalesException;
use App\Domains\Sales\Services\SalesService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SaleAgreementController
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-sales');
        $status = $request->string('status')->toString();

        return Inertia::render('sales/agreements', [
            'agreements' => SaleAgreement::query()->with(['unit.project:id,ulid,code', 'buyer:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByDesc('signed_on')->paginate(25)->withQueryString()
                ->through(static fn (SaleAgreement $a): array => [
                    'id' => $a->ulid, 'reference' => $a->reference(), 'unit' => $a->unit->reference, 'project' => $a->unit->project->code,
                    'buyer' => $a->buyer->name, 'signed' => $a->signed_on->toDateString(), 'price' => (float) $a->purchase_price,
                    'status' => $a->status, 'registered' => $a->registered_on?->toDateString(), 'commissionStatus' => $a->commission_status,
                ]),
            'filters' => ['status' => $status ?: null],
        ]);
    }

    public function show(SaleAgreement $agreement): Response
    {
        Gate::authorize('view-sales');
        $agreement->load(['unit.project:id,ulid,code,name', 'buyer', 'agency:id,name', 'conveyancer:id,name', 'conditions', 'steps']);
        /** @var array<string, string> $stepLabels */
        $stepLabels = (array) config('sales.transfer_steps');

        return Inertia::render('sales/agreement', [
            'agreement' => [
                'id' => $agreement->ulid, 'reference' => $agreement->reference(), 'status' => $agreement->status,
                'project' => ['id' => $agreement->unit->project->ulid, 'code' => $agreement->unit->project->code, 'name' => $agreement->unit->project->name],
                'unit' => $agreement->unit->reference, 'unitType' => $agreement->unit->type, 'nhbrc' => $agreement->unit->nhbrc_enrolment,
                'buyer' => ['id' => $agreement->buyer->ulid, 'name' => $agreement->buyer->name, 'fica' => $agreement->buyer->fica_verified],
                'signed' => $agreement->signed_on->toDateString(), 'price' => (float) $agreement->purchase_price, 'netPrice' => $agreement->netPrice(),
                'vat' => $agreement->vat_applies, 'deposit' => (float) $agreement->deposit_amount, 'depositDue' => $agreement->deposit_due_on?->toDateString(),
                'depositReceived' => $agreement->deposit_received_on?->toDateString(), 'depositHeldBy' => $agreement->deposit_held_by,
                'trustRef' => $agreement->trust_account_ref, 'bondRequired' => $agreement->bond_required,
                'bondAmount' => $agreement->bond_amount === null ? null : (float) $agreement->bond_amount, 'bondOriginator' => $agreement->bond_originator,
                'occupation' => $agreement->occupation_date?->toDateString(), 'agency' => $agreement->agency?->name, 'conveyancer' => $agreement->conveyancer?->name,
                'commissionPercent' => $agreement->commission_percent === null ? null : (float) $agreement->commission_percent,
                'commissionAmount' => $agreement->commission_amount === null ? null : (float) $agreement->commission_amount,
                'commissionStatus' => $agreement->commission_status, 'registered' => $agreement->registered_on?->toDateString(), 'notes' => $agreement->notes,
            ],
            'conditions' => $agreement->conditions->map(static fn (SaleCondition $c): array => [
                'id' => $c->id, 'type' => $c->type, 'description' => $c->description, 'due' => $c->due_on->toDateString(),
                'status' => $c->status, 'resolved' => $c->resolved_on?->toDateString(), 'notes' => $c->notes,
                'overdue' => $c->status === 'open' && $c->due_on->isPast(),
            ])->values(),
            'steps' => $agreement->steps->map(static fn (TransferStep $s): array => [
                'id' => $s->id, 'step' => $s->step, 'label' => $stepLabels[$s->step] ?? $s->step,
                'completed' => $s->completed_on?->toDateString(), 'notes' => $s->notes,
            ])->values(),
            'canManage' => Gate::allows('manage-sales'),
        ]);
    }

    public function updateDeposit(Request $request, SaleAgreement $agreement): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'deposit_received_on' => ['nullable', 'date'],
            'deposit_held_by' => ['nullable', 'string', 'max:190'],
            'trust_account_ref' => ['nullable', 'string', 'max:60'],
        ]);
        $agreement->update($data);

        return back()->with('success', 'Deposit details saved.');
    }

    public function resolveCondition(Request $request, SaleCondition $condition): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate([
            'status' => ['required', 'in:met,waived,failed'],
            'resolved_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'required_if:status,failed,waived', 'string', 'max:500'],
        ], ['notes.required_if' => 'Record why the condition was waived or failed.']);

        $agreement = $this->sales->resolveCondition($condition, (string) $data['status'],
            isset($data['resolved_on']) ? Carbon::parse((string) $data['resolved_on']) : null, $data['notes'] ?? null);

        return back()->with('success', match ($agreement->fresh()?->status) {
            'unconditional' => 'All conditions are met: the sale is now unconditional.',
            'lapsed' => 'The condition failed, so the sale has lapsed and the unit is available again.',
            default => 'Condition updated.',
        });
    }

    public function completeStep(Request $request, TransferStep $step): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate(['completed_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->sales->completeStep($step, isset($data['completed_on']) ? Carbon::parse((string) $data['completed_on']) : null, $data['notes'] ?? null, $user);
        } catch (SalesException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $step->step === 'registration' ? 'Transfer registered. The unit is now transferred and commission can be approved.' : 'Step recorded.');
    }

    public function approveCommission(Request $request, SaleAgreement $agreement): RedirectResponse
    {
        Gate::authorize('approve-commission');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->sales->approveCommission($agreement, $user);
        } catch (SalesException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Commission approved for payment.');
    }
}
