<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use Illuminate\Support\Carbon;

/**
 * CSV exports for the accounting and payroll systems. Column layouts follow Sage Business Cloud's supplier
 * invoice import and a generic payroll input layout (SimplePay and others map columns on import).
 * Confirm the mapping with the client's accountant before first use.
 */
final class ExportService
{
    public function supplierInvoices(Carbon $from, Carbon $to): string
    {
        $rows = [['Supplier', 'Supplier VAT number', 'Document date', 'Due date', 'Reference', 'Description', 'Account (cost code)', 'Project', 'Exclusive amount', 'VAT amount', 'Inclusive amount', 'Status']];

        SupplierInvoice::query()->with(['supplier:id,name,vat_number', 'project:id,code', 'budgetLine:id,code', 'purchaseOrder:id,number'])
            ->whereIn('status', ['approved', 'scheduled', 'paid'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('invoice_date')->get()
            ->each(function (SupplierInvoice $i) use (&$rows): void {
                $rows[] = [
                    $i->supplier->name, (string) $i->supplier->vat_number, $i->invoice_date->format('d/m/Y'), $i->due_date->format('d/m/Y'),
                    $i->invoice_number, $i->purchaseOrder ? $i->purchaseOrder->reference() : 'No order', (string) $i->budgetLine?->code,
                    $i->project->code, $this->money($i->subtotal), $this->money($i->vat), $this->money($i->total), $i->status,
                ];
            });

        return $this->csv($rows);
    }

    public function payments(Carbon $from, Carbon $to): string
    {
        $rows = [['Supplier', 'Payment date', 'Reference', 'Amount', 'Payment run']];

        SupplierInvoice::query()->with(['supplier:id,name', 'paymentRun'])->where('status', 'paid')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('paid_at')->get()
            ->each(function (SupplierInvoice $i) use (&$rows): void {
                $rows[] = [$i->supplier->name, $i->paid_at?->format('d/m/Y') ?? '', $i->invoice_number, $this->money($i->total), $i->paymentRun?->reference() ?? ''];
            });

        return $this->csv($rows);
    }

    public function payrollInputs(Carbon $from, Carbon $to): string
    {
        $rows = [['Employee number', 'Employee', 'Item', 'Date', 'Quantity', 'Rate multiplier', 'Project']];

        OvertimeEntry::query()->with(['employee', 'project:id,code'])->where('status', 'approved')
            ->whereBetween('worked_on', [$from->toDateString(), $to->toDateString()])->orderBy('worked_on')->get()
            ->each(function (OvertimeEntry $o) use (&$rows): void {
                $rows[] = [$o->employee->employee_number, $o->employee->name(), 'Overtime hours', $o->worked_on->format('d/m/Y'), (string) (float) $o->hours, (string) (float) $o->rate_multiplier, (string) $o->project?->code];
            });

        LeaveRequest::query()->with('employee')->where('status', 'approved')
            ->whereBetween('from_date', [$from->toDateString(), $to->toDateString()])->orderBy('from_date')->get()
            ->each(function (LeaveRequest $l) use (&$rows): void {
                $rows[] = [$l->employee->employee_number, $l->employee->name(), ucfirst($l->type).' leave days', $l->from_date->format('d/m/Y'), (string) (float) $l->days, '', ''];
            });

        return $this->csv($rows);
    }

    private function money(string|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
