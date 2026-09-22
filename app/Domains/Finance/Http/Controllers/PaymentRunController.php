<?php

declare(strict_types=1);

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\PaymentRun;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\PaymentRunService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentRunController
{
    public function __construct(private readonly PaymentRunService $runs) {}

    public function index(): Response
    {
        Gate::authorize('manage-finance');

        return Inertia::render('finance/payment-runs', [
            'runs' => PaymentRun::query()->withCount('invoices')->latest('id')->limit(50)->get()->map(static fn (PaymentRun $r): array => [
                'id' => $r->ulid, 'reference' => $r->reference(), 'payOn' => $r->pay_on->toDateString(), 'status' => $r->status,
                'total' => (float) $r->total, 'invoices' => (int) $r->getAttribute('invoices_count'),
            ]),
            'ready' => [
                'count' => SupplierInvoice::query()->where('status', 'approved')->whereNull('payment_run_id')->count(),
                'total' => (float) SupplierInvoice::query()->where('status', 'approved')->whereNull('payment_run_id')->sum('total'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-finance');
        $data = $request->validate(['pay_on' => ['required', 'date', 'after_or_equal:today']]);
        /** @var User $user */
        $user = $request->user();

        try {
            ['run' => $run, 'blocked' => $blocked] = $this->runs->create((string) $data['pay_on'], $user);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        $note = $blocked ? ' Left out because the supplier is not compliant: '.implode(', ', array_unique(array_column($blocked, 'supplier'))).'.' : '';

        return redirect()->route('payment-runs.show', $run)->with($blocked ? 'error' : 'success', "{$run->reference()} prepared.{$note}");
    }

    public function show(PaymentRun $run): Response
    {
        Gate::authorize('manage-finance');
        $run->load(['invoices.supplier:id,name', 'invoices.project:id,name', 'creator:id,name']);
        $approval = $run->morphMany(ApprovalRequest::class, 'approvable')->latest('id')->with('steps.decider:id,name')->first();

        return Inertia::render('finance/payment-run', [
            'run' => [
                'id' => $run->ulid, 'reference' => $run->reference(), 'payOn' => $run->pay_on->toDateString(), 'status' => $run->status,
                'total' => (float) $run->total, 'by' => $run->creator->name, 'paidAt' => $run->paid_at?->toIso8601String(),
            ],
            'invoices' => $run->invoices->map(static fn (SupplierInvoice $i): array => [
                'id' => $i->ulid, 'number' => $i->invoice_number, 'supplier' => $i->supplier->name, 'project' => $i->project->name,
                'due' => $i->due_date->toDateString(), 'total' => (float) $i->total, 'status' => $i->status,
            ]),
            'approval' => $approval ? [
                'status' => $approval->status,
                'steps' => $approval->steps->map(static fn (ApprovalStep $s): array => [
                    'sequence' => $s->sequence, 'role' => Role::tryFrom($s->role)?->label() ?? $s->role, 'decision' => $s->decision,
                    'by' => $s->decider?->name, 'at' => $s->decided_at?->toIso8601String(), 'comment' => $s->comment,
                    'current' => $approval->status === 'pending' && $approval->current_step === $s->sequence,
                ])->values(),
            ] : null,
        ]);
    }

    public function submit(Request $request, PaymentRun $run): RedirectResponse
    {
        Gate::authorize('manage-finance');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->runs->submit($run, $user);
        } catch (FinanceException|ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sent to a Director for approval.');
    }

    public function paid(PaymentRun $run): RedirectResponse
    {
        Gate::authorize('manage-finance');

        try {
            $removed = $this->runs->markPaid($run);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($removed ? 'error' : 'success', $removed
            ? 'Marked as paid, except for suppliers who are no longer compliant: '.implode(', ', $removed).'. Their invoices are back in the queue.'
            : "{$run->reference()} marked as paid.");
    }

    public function export(PaymentRun $run): \Illuminate\Http\Response
    {
        Gate::authorize('manage-finance');
        abort_unless(in_array($run->status, ['approved', 'paid'], true), 403, 'Only approved payment runs can be exported.');

        return response($this->runs->csv($run), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$run->reference().'.csv"',
        ]);
    }
}
