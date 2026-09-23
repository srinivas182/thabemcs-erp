<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Data sources for the report designer. Each dataset lists its fields (with a type used for formatting
 * and totals), the permission needed to use it, and produces plain rows. Rows are limited to 5 000.
 */
final class DatasetRegistry
{
    private const int LIMIT = 5000;

    public function __construct(private readonly BudgetService $budgets, private readonly ComplianceService $compliance) {}

    /**
     * Each field says which database column filters and sorts it; fields without one (figures worked out
     * in PHP, such as revised budget or supplier compliance) are filtered after the rows are fetched.
     *
     * @return array<string, array{label: string, gate: string, fields: array<string, array{label: string, type: 'text'|'money'|'number'|'percent'|'date', column?: string}>}>
     */
    public function definitions(): array
    {
        return [
            'purchase_orders' => ['label' => 'Purchase orders', 'gate' => 'view-financial-reports', 'fields' => [
                'reference' => ['label' => 'Order', 'type' => 'text', 'column' => 'purchase_orders.number'], 'project' => ['label' => 'Project', 'type' => 'text', 'column' => 'projects.code'], 'supplier' => ['label' => 'Supplier', 'type' => 'text', 'column' => 'suppliers.name'],
                'status' => ['label' => 'Status', 'type' => 'text', 'column' => 'purchase_orders.status'], 'subtotal' => ['label' => 'Amount excl. VAT', 'type' => 'money', 'column' => 'purchase_orders.subtotal'], 'total' => ['label' => 'Total incl. VAT', 'type' => 'money', 'column' => 'purchase_orders.total'],
                'expected' => ['label' => 'Expected delivery', 'type' => 'date', 'column' => 'purchase_orders.expected_delivery'], 'issued' => ['label' => 'Issued', 'type' => 'date', 'column' => 'purchase_orders.issued_at'],
            ]],
            'supplier_invoices' => ['label' => 'Supplier invoices', 'gate' => 'view-financial-reports', 'fields' => [
                'number' => ['label' => 'Invoice', 'type' => 'text', 'column' => 'supplier_invoices.invoice_number'], 'supplier' => ['label' => 'Supplier', 'type' => 'text', 'column' => 'suppliers.name'], 'project' => ['label' => 'Project', 'type' => 'text', 'column' => 'projects.code'],
                'status' => ['label' => 'Status', 'type' => 'text', 'column' => 'supplier_invoices.status'], 'invoice_date' => ['label' => 'Invoice date', 'type' => 'date', 'column' => 'supplier_invoices.invoice_date'], 'due_date' => ['label' => 'Due date', 'type' => 'date', 'column' => 'supplier_invoices.due_date'],
                'subtotal' => ['label' => 'Amount excl. VAT', 'type' => 'money', 'column' => 'supplier_invoices.subtotal'], 'vat' => ['label' => 'VAT', 'type' => 'money', 'column' => 'supplier_invoices.vat'], 'total' => ['label' => 'Total', 'type' => 'money', 'column' => 'supplier_invoices.total'],
            ]],
            'budget_lines' => ['label' => 'Budget by cost code', 'gate' => 'view-financial-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text', 'column' => 'projects.code'], 'code' => ['label' => 'Code', 'type' => 'text', 'column' => 'budget_lines.code'], 'description' => ['label' => 'Cost code', 'type' => 'text', 'column' => 'budget_lines.description'],
                'original' => ['label' => 'Original', 'type' => 'money', 'column' => 'budget_lines.original_amount'], 'variations' => ['label' => 'Variations', 'type' => 'money'], 'revised' => ['label' => 'Revised', 'type' => 'money'],
                'committed' => ['label' => 'Committed', 'type' => 'money'], 'direct' => ['label' => 'Direct', 'type' => 'money'], 'available' => ['label' => 'Available', 'type' => 'money'], 'used' => ['label' => 'Used', 'type' => 'percent'],
            ]],
            'suppliers' => ['label' => 'Suppliers', 'gate' => 'view-financial-reports', 'fields' => [
                'name' => ['label' => 'Supplier', 'type' => 'text', 'column' => 'suppliers.name'], 'type' => ['label' => 'Type', 'type' => 'text', 'column' => 'suppliers.type'], 'cidb' => ['label' => 'CIDB grade', 'type' => 'text', 'column' => 'suppliers.cidb_grade'],
                'bbbee' => ['label' => 'B-BBEE level', 'type' => 'text', 'column' => 'suppliers.bbbee_level'], 'compliant' => ['label' => 'Compliant', 'type' => 'text'], 'status' => ['label' => 'Status', 'type' => 'text', 'column' => 'suppliers.status'],
                'contact' => ['label' => 'Contact', 'type' => 'text', 'column' => 'suppliers.contact_name'], 'email' => ['label' => 'Email', 'type' => 'text', 'column' => 'suppliers.email'], 'phone' => ['label' => 'Phone', 'type' => 'text', 'column' => 'suppliers.phone'],
            ]],
            'incidents' => ['label' => 'Safety incidents', 'gate' => 'view-safety-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text', 'column' => 'projects.code'], 'type' => ['label' => 'Type', 'type' => 'text', 'column' => 'safety_incidents.type'], 'date' => ['label' => 'Date', 'type' => 'date', 'column' => 'safety_incidents.occurred_at'],
                'location' => ['label' => 'Location', 'type' => 'text', 'column' => 'safety_incidents.location'], 'reportable' => ['label' => 'Reportable', 'type' => 'text', 'column' => 'safety_incidents.reportable'], 'status' => ['label' => 'Status', 'type' => 'text', 'column' => 'safety_incidents.status'],
            ]],
            'employees' => ['label' => 'Employees', 'gate' => 'manage-workforce', 'fields' => [
                'number' => ['label' => 'Employee no.', 'type' => 'text', 'column' => 'employees.employee_number'], 'name' => ['label' => 'Name', 'type' => 'text', 'column' => 'employees.last_name'], 'job' => ['label' => 'Job title', 'type' => 'text', 'column' => 'employees.job_title'],
                'employment' => ['label' => 'Employment', 'type' => 'text', 'column' => 'employees.employment_type'], 'status' => ['label' => 'Status', 'type' => 'text', 'column' => 'employees.status'], 'start' => ['label' => 'Start date', 'type' => 'date', 'column' => 'employees.start_date'],
            ]],
            'activities' => ['label' => 'Programme activities', 'gate' => 'view-financial-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text', 'column' => 'projects.code'], 'wbs' => ['label' => 'WBS', 'type' => 'text', 'column' => 'programme_activities.wbs'], 'name' => ['label' => 'Activity', 'type' => 'text', 'column' => 'programme_activities.name'],
                'start' => ['label' => 'Earliest start', 'type' => 'date', 'column' => 'programme_activities.planned_start'], 'duration' => ['label' => 'Working days', 'type' => 'number', 'column' => 'programme_activities.duration_days'], 'percent' => ['label' => 'Complete', 'type' => 'percent', 'column' => 'programme_activities.percent_complete'],
            ]],
        ];
    }

    /**
     * Rows for a dataset. Filters and sorting on real columns run in the database, so a filtered report
     * never silently misses rows beyond the limit; the total says whether it was cut short.
     *
     * @param  list<array{field: string, op: string, value: string}>  $filters
     * @return array{rows: list<array<string, string|int|float|null>>, total: int, truncated: bool, inPhp: list<array{field: string, op: string, value: string}>}
     */
    public function rows(string $dataset, ?int $projectId, array $filters = [], ?string $sort = null, string $direction = 'asc', ?int $limit = null): array
    {
        $limit ??= (int) config('reporting.row_limit', self::LIMIT);
        $fields = $this->definitions()[$dataset]['fields'] ?? [];
        /** @var Builder<Model> $query */
        /** @var callable(mixed): array<string, string|int|float|null> $map */
        [$query, $map] = $this->source($dataset);

        if ($projectId !== null && $this->hasProjectFilter($dataset)) {
            $query->where($this->table($dataset).'.project_id', $projectId);
        }

        $inPhp = [];
        foreach ($filters as $f) {
            $column = $fields[$f['field']]['column'] ?? null;
            if ($column === null) {
                $inPhp[] = $f;

                continue;
            }
            $value = $f['value'];
            match ($f['op']) {
                'eq' => $query->where($column, '=', $value),
                'ne' => $query->where($column, '!=', $value),
                'contains' => $query->where($column, 'like', '%'.addcslashes($value, '%_\\').'%'),
                'gte' => $query->where($column, '>=', $value),
                'lte' => $query->where($column, '<=', $value),
                default => null,
            };
        }

        $total = (clone $query)->toBase()->getCountForPagination();
        if ($sort !== null && isset($fields[$sort]['column'])) {
            $query->reorder()->orderBy($fields[$sort]['column'], $direction === 'desc' ? 'desc' : 'asc');
        }

        /** @var list<array<string, string|int|float|null>> $rows */
        $rows = array_values($query->limit($limit)->get()->map($map)->all());

        return ['rows' => $rows, 'total' => $total, 'truncated' => $total > $limit, 'inPhp' => $inPhp];
    }

    private function table(string $dataset): string
    {
        return match ($dataset) {
            'incidents' => 'safety_incidents',
            'activities' => 'programme_activities',
            default => $dataset,
        };
    }

    /**
     * The query and row mapping for a dataset. Related names are joined so they can be filtered and
     * sorted in the database too.
     *
     * @return array{0: Builder<covariant \Illuminate\Database\Eloquent\Model>, 1: callable(mixed): array<string, string|int|float|null>}
     */
    private function source(string $dataset): array
    {
        return match ($dataset) {
            'purchase_orders' => [
                PurchaseOrder::query()->with(['project:id,code', 'supplier:id,name'])
                    ->join('projects', 'projects.id', '=', 'purchase_orders.project_id')
                    ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                    ->select('purchase_orders.*')->orderBy('purchase_orders.number'),
                static fn (PurchaseOrder $o): array => ['reference' => $o->reference(), 'project' => $o->project->code, 'supplier' => $o->supplier->name, 'status' => str_replace('_', ' ', $o->status),
                    'subtotal' => (float) $o->subtotal, 'total' => (float) $o->total, 'expected' => $o->expected_delivery?->toDateString(), 'issued' => $o->issued_at?->toDateString()],
            ],
            'supplier_invoices' => [
                SupplierInvoice::query()->with(['project:id,code', 'supplier:id,name'])
                    ->join('projects', 'projects.id', '=', 'supplier_invoices.project_id')
                    ->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
                    ->select('supplier_invoices.*')->orderBy('supplier_invoices.invoice_date'),
                static fn (SupplierInvoice $i): array => ['number' => $i->invoice_number, 'supplier' => $i->supplier->name, 'project' => $i->project->code, 'status' => $i->status,
                    'invoice_date' => $i->invoice_date->toDateString(), 'due_date' => $i->due_date->toDateString(), 'subtotal' => (float) $i->subtotal, 'vat' => (float) $i->vat, 'total' => (float) $i->total],
            ],
            'budget_lines' => [
                BudgetLine::query()->with('project:id,code')
                    ->join('projects', 'projects.id', '=', 'budget_lines.project_id')->select('budget_lines.*')
                    ->orderBy('projects.code')->orderBy('budget_lines.code'),
                fn (BudgetLine $l): array => ['project' => $l->project->code, ...array_diff_key($this->budgets->figures($l), ['id' => 1])],
            ],
            'suppliers' => [
                Supplier::query()->orderBy('suppliers.name'),
                fn (Supplier $s): array => ['name' => $s->name, 'type' => $s->type->label(), 'cidb' => $s->cidb_grade ? "{$s->cidb_grade}{$s->cidb_class}" : null, 'bbbee' => $s->bbbee_level,
                    'compliant' => $this->compliance->isCompliant($s) ? 'Yes' : 'No', 'status' => $s->status, 'contact' => $s->contact_name, 'email' => $s->email, 'phone' => $s->phone],
            ],
            'incidents' => [
                SafetyIncident::query()->with('project:id,code')
                    ->join('projects', 'projects.id', '=', 'safety_incidents.project_id')->select('safety_incidents.*')->orderBy('safety_incidents.occurred_at'),
                static fn (SafetyIncident $i): array => ['project' => $i->project->code, 'type' => $i->type->label(), 'date' => $i->occurred_at->toDateString(), 'location' => $i->location,
                    'reportable' => $i->reportable ? 'Yes' : 'No', 'status' => $i->status],
            ],
            'employees' => [
                Employee::query()->orderBy('employees.last_name'),
                static fn (Employee $e): array => ['number' => $e->employee_number, 'name' => $e->name(), 'job' => $e->job_title, 'employment' => str_replace('_', ' ', $e->employment_type),
                    'status' => $e->status, 'start' => $e->start_date->toDateString()],
            ],
            'activities' => [
                ProgrammeActivity::query()->with('project:id,code')
                    ->join('projects', 'projects.id', '=', 'programme_activities.project_id')->select('programme_activities.*')
                    ->orderBy('projects.code')->orderBy('programme_activities.sort'),
                static fn (ProgrammeActivity $a): array => ['project' => $a->project->code, 'wbs' => $a->wbs, 'name' => $a->name, 'start' => $a->planned_start->toDateString(),
                    'duration' => $a->duration_days, 'percent' => $a->percent_complete],
            ],
            default => throw new InvalidArgumentException("Unknown dataset {$dataset}."),
        };
    }

    public function hasProjectFilter(string $dataset): bool
    {
        return in_array($dataset, ['purchase_orders', 'supplier_invoices', 'budget_lines', 'incidents', 'activities'], true);
    }
}
