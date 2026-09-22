<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\PaymentRun;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Batches approved invoices for payment. Non-compliant suppliers are left out and listed,
 * compliance is checked again before payment, and the run is approved through the approval engine.
 */
final class PaymentRunService
{
    public function __construct(private readonly ComplianceService $compliance, private readonly ApprovalEngine $approvals) {}

    /**
     * @return array{run: PaymentRun, blocked: list<array{invoice: string, supplier: string, reasons: list<string>}>}
     *
     * @throws FinanceException
     */
    public function create(string $payOn, User $by): array
    {
        $candidates = SupplierInvoice::query()->with('supplier')->where('status', 'approved')->whereNull('payment_run_id')
            ->whereDate('due_date', '<=', $payOn)->orderBy('due_date')->get();

        $blocked = [];
        $eligible = $candidates->filter(function (SupplierInvoice $i) use (&$blocked): bool {
            $reasons = $this->compliance->blockers($i->supplier);
            if ($reasons !== []) {
                $blocked[] = ['invoice' => $i->invoice_number, 'supplier' => $i->supplier->name, 'reasons' => $reasons];

                return false;
            }

            return true;
        });

        if ($eligible->isEmpty()) {
            throw new FinanceException($blocked
                ? 'Every invoice due is from a supplier who is not compliant. Resolve their documents first.'
                : 'No approved invoices are due by that date.');
        }

        $run = DB::transaction(function () use ($eligible, $payOn, $by): PaymentRun {
            $run = PaymentRun::query()->create([
                'number' => (int) PaymentRun::query()->lockForUpdate()->max('number') + 1,
                'pay_on' => $payOn, 'status' => 'draft', 'total' => round((float) $eligible->sum('total'), 2), 'created_by' => $by->id,
            ]);
            SupplierInvoice::query()->whereIn('id', $eligible->pluck('id'))->update(['payment_run_id' => $run->id, 'status' => 'scheduled']);

            return $run;
        });

        return ['run' => $run, 'blocked' => $blocked];
    }

    /**
     * @throws FinanceException
     * @throws ApprovalException
     */
    public function submit(PaymentRun $run, User $by): void
    {
        if ($run->status !== 'draft') {
            throw new FinanceException('Only draft payment runs can be submitted.');
        }
        DB::transaction(function () use ($run, $by): void {
            $run->forceFill(['status' => 'pending_approval'])->save();
            $this->approvals->submit($run, 'payment_run', (float) $run->total, $by);
        });
    }

    /**
     * Record that the bank payments were made. Suppliers who became non-compliant since the run was
     * approved are taken out and their invoices go back to "approved".
     *
     * @return list<string> suppliers removed
     *
     * @throws FinanceException
     */
    public function markPaid(PaymentRun $run): array
    {
        if ($run->status !== 'approved') {
            throw new FinanceException('The payment run must be approved before it is paid.');
        }

        $removed = [];
        DB::transaction(function () use ($run, &$removed): void {
            foreach ($run->invoices()->with('supplier')->get() as $invoice) {
                if ($this->compliance->blockers($invoice->supplier) !== []) {
                    $invoice->forceFill(['payment_run_id' => null, 'status' => 'approved'])->save();
                    $removed[] = $invoice->supplier->name;

                    continue;
                }
                $invoice->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            }
            $run->forceFill(['status' => 'paid', 'paid_at' => now(), 'total' => round((float) $run->invoices()->sum('total'), 2)])->save();
        });

        return array_values(array_unique($removed));
    }

    /**
     * Payment schedule for loading into online banking (one line per supplier).
     */
    public function csv(PaymentRun $run): string
    {
        $rows = [['Beneficiary', 'Registration number', 'Amount', 'Our reference', 'Invoices']];
        $bySupplier = $run->invoices()->with('supplier')->get()->groupBy('supplier_id');

        foreach ($bySupplier as $invoices) {
            $supplier = $invoices->first()?->supplier;
            if ($supplier === null) {
                continue;
            }
            $rows[] = [
                $supplier->name, (string) $supplier->registration_number,
                number_format((float) $invoices->sum('total'), 2, '.', ''), $run->reference(),
                $invoices->pluck('invoice_number')->implode(' '),
            ];
        }

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
