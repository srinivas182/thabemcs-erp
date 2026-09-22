<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use App\Domains\Suppliers\Enums\ComplianceState;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Services\ComplianceService;

final class SupplierComplianceReport implements Report
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function key(): string
    {
        return 'supplier-compliance';
    }

    public function title(): string
    {
        return 'Supplier compliance';
    }

    public function description(): string
    {
        return 'Compliance documents that are missing, expired or expiring within 30 days, per active supplier.';
    }

    public function gate(): string
    {
        return 'view-financial-reports';
    }

    public function filters(): array
    {
        return [];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $rows = [];
        foreach (Supplier::query()->where('status', 'active')->orderBy('name')->get() as $supplier) {
            foreach ($this->compliance->evaluate($supplier) as $doc) {
                if ($doc['state'] === ComplianceState::Valid) {
                    continue;
                }
                $rows[] = [
                    'supplier' => $supplier->name, 'type' => $supplier->type->label(), 'document' => $doc['label'],
                    'state' => match ($doc['state']) {
                        ComplianceState::Missing => 'Missing', ComplianceState::Expired => 'Expired', default => 'Expiring'
                    },
                    'expires' => $doc['expiresOn'], 'blocks' => $doc['block'] ? 'Yes' : 'No',
                ];
            }
        }

        return new ReportResult($this->title(), 'All active suppliers, as at '.now()->format('j M Y'), [
            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'text'], ['key' => 'type', 'label' => 'Type', 'type' => 'text'],
            ['key' => 'document', 'label' => 'Document', 'type' => 'text'], ['key' => 'state', 'label' => 'Problem', 'type' => 'text'],
            ['key' => 'expires', 'label' => 'Expires', 'type' => 'date'], ['key' => 'blocks', 'label' => 'Blocks orders and payment', 'type' => 'text'],
        ], $rows, null, 'Suppliers with a blocking problem cannot be appointed or paid.');
    }
}
