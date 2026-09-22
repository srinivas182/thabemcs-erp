<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

final class CommitmentsReport implements Report
{
    public function key(): string
    {
        return 'commitments';
    }

    public function title(): string
    {
        return 'Commitments';
    }

    public function description(): string
    {
        return 'Approved purchase orders with what has been received, invoiced and is still to come (excl. VAT).';
    }

    public function gate(): string
    {
        return 'view-financial-reports';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $rows = PurchaseOrder::query()->with(['supplier:id,name', 'project:id,code', 'lines'])
            ->whereIn('status', BudgetService::COMMITTED)
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->orderBy('number')->get()
            ->map(static function (PurchaseOrder $o): array {
                $received = round((float) $o->lines->sum(static fn (PurchaseOrderLine $l): float => (float) $l->received_quantity * (float) $l->unit_price), 2);
                $invoiced = (float) SupplierInvoice::query()->where('purchase_order_id', $o->id)->where('status', '!=', 'rejected')->sum('subtotal');

                return [
                    'po' => $o->reference(), 'project' => $o->project->code, 'supplier' => $o->supplier->name, 'status' => str_replace('_', ' ', $o->status),
                    'ordered' => (float) $o->subtotal, 'received' => $received, 'invoiced' => round($invoiced, 2),
                    'outstanding' => round((float) $o->subtotal - $invoiced, 2),
                ];
            })->values()->all();

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'po', 'label' => 'Order', 'type' => 'text'], ['key' => 'project', 'label' => 'Project', 'type' => 'text'],
            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'text'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ['key' => 'ordered', 'label' => 'Ordered', 'type' => 'money'], ['key' => 'received', 'label' => 'Received', 'type' => 'money'],
            ['key' => 'invoiced', 'label' => 'Invoiced', 'type' => 'money'], ['key' => 'outstanding', 'label' => 'Still to invoice', 'type' => 'money'],
        ], $rows, ['po' => 'Total', ...ReportResult::sum($rows, ['ordered', 'received', 'invoiced', 'outstanding'])]);
    }
}
