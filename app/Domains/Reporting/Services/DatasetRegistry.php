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

/**
 * Data sources for the report designer. Each dataset lists its fields (with a type used for formatting
 * and totals), the permission needed to use it, and produces plain rows. Rows are limited to 5 000.
 */
final class DatasetRegistry
{
    private const int LIMIT = 5000;

    public function __construct(private readonly BudgetService $budgets, private readonly ComplianceService $compliance) {}

    /**
     * @return array<string, array{label: string, gate: string, fields: array<string, array{label: string, type: 'text'|'money'|'number'|'percent'|'date'}>}>
     */
    public function definitions(): array
    {
        return [
            'purchase_orders' => ['label' => 'Purchase orders', 'gate' => 'view-financial-reports', 'fields' => [
                'reference' => ['label' => 'Order', 'type' => 'text'], 'project' => ['label' => 'Project', 'type' => 'text'], 'supplier' => ['label' => 'Supplier', 'type' => 'text'],
                'status' => ['label' => 'Status', 'type' => 'text'], 'subtotal' => ['label' => 'Amount excl. VAT', 'type' => 'money'], 'total' => ['label' => 'Total incl. VAT', 'type' => 'money'],
                'expected' => ['label' => 'Expected delivery', 'type' => 'date'], 'issued' => ['label' => 'Issued', 'type' => 'date'],
            ]],
            'supplier_invoices' => ['label' => 'Supplier invoices', 'gate' => 'view-financial-reports', 'fields' => [
                'number' => ['label' => 'Invoice', 'type' => 'text'], 'supplier' => ['label' => 'Supplier', 'type' => 'text'], 'project' => ['label' => 'Project', 'type' => 'text'],
                'status' => ['label' => 'Status', 'type' => 'text'], 'invoice_date' => ['label' => 'Invoice date', 'type' => 'date'], 'due_date' => ['label' => 'Due date', 'type' => 'date'],
                'subtotal' => ['label' => 'Amount excl. VAT', 'type' => 'money'], 'vat' => ['label' => 'VAT', 'type' => 'money'], 'total' => ['label' => 'Total', 'type' => 'money'],
            ]],
            'budget_lines' => ['label' => 'Budget by cost code', 'gate' => 'view-financial-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text'], 'code' => ['label' => 'Code', 'type' => 'text'], 'description' => ['label' => 'Cost code', 'type' => 'text'],
                'original' => ['label' => 'Original', 'type' => 'money'], 'variations' => ['label' => 'Variations', 'type' => 'money'], 'revised' => ['label' => 'Revised', 'type' => 'money'],
                'committed' => ['label' => 'Committed', 'type' => 'money'], 'direct' => ['label' => 'Direct', 'type' => 'money'], 'available' => ['label' => 'Available', 'type' => 'money'], 'used' => ['label' => 'Used', 'type' => 'percent'],
            ]],
            'suppliers' => ['label' => 'Suppliers', 'gate' => 'view-financial-reports', 'fields' => [
                'name' => ['label' => 'Supplier', 'type' => 'text'], 'type' => ['label' => 'Type', 'type' => 'text'], 'cidb' => ['label' => 'CIDB grade', 'type' => 'text'],
                'bbbee' => ['label' => 'B-BBEE level', 'type' => 'text'], 'compliant' => ['label' => 'Compliant', 'type' => 'text'], 'status' => ['label' => 'Status', 'type' => 'text'],
                'contact' => ['label' => 'Contact', 'type' => 'text'], 'email' => ['label' => 'Email', 'type' => 'text'], 'phone' => ['label' => 'Phone', 'type' => 'text'],
            ]],
            'incidents' => ['label' => 'Safety incidents', 'gate' => 'view-safety-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text'], 'type' => ['label' => 'Type', 'type' => 'text'], 'date' => ['label' => 'Date', 'type' => 'date'],
                'location' => ['label' => 'Location', 'type' => 'text'], 'reportable' => ['label' => 'Reportable', 'type' => 'text'], 'status' => ['label' => 'Status', 'type' => 'text'],
            ]],
            'employees' => ['label' => 'Employees', 'gate' => 'manage-workforce', 'fields' => [
                'number' => ['label' => 'Employee no.', 'type' => 'text'], 'name' => ['label' => 'Name', 'type' => 'text'], 'job' => ['label' => 'Job title', 'type' => 'text'],
                'employment' => ['label' => 'Employment', 'type' => 'text'], 'status' => ['label' => 'Status', 'type' => 'text'], 'start' => ['label' => 'Start date', 'type' => 'date'],
            ]],
            'activities' => ['label' => 'Programme activities', 'gate' => 'view-financial-reports', 'fields' => [
                'project' => ['label' => 'Project', 'type' => 'text'], 'wbs' => ['label' => 'WBS', 'type' => 'text'], 'name' => ['label' => 'Activity', 'type' => 'text'],
                'start' => ['label' => 'Earliest start', 'type' => 'date'], 'duration' => ['label' => 'Working days', 'type' => 'number'], 'percent' => ['label' => 'Complete', 'type' => 'percent'],
            ]],
        ];
    }

    /**
     * @return list<array<string, string|int|float|null>>
     */
    public function rows(string $dataset, ?int $projectId): array
    {
        $p = static fn ($q) => $projectId ? $q->where('project_id', $projectId) : $q;

        $rows = match ($dataset) {
            'purchase_orders' => $p(PurchaseOrder::query()->with(['project:id,code', 'supplier:id,name']))->orderBy('number')->limit(self::LIMIT)->get()
                ->map(static fn (PurchaseOrder $o): array => ['reference' => $o->reference(), 'project' => $o->project->code, 'supplier' => $o->supplier->name, 'status' => str_replace('_', ' ', $o->status),
                    'subtotal' => (float) $o->subtotal, 'total' => (float) $o->total, 'expected' => $o->expected_delivery?->toDateString(), 'issued' => $o->issued_at?->toDateString()]),
            'supplier_invoices' => $p(SupplierInvoice::query()->with(['project:id,code', 'supplier:id,name']))->orderBy('invoice_date')->limit(self::LIMIT)->get()
                ->map(static fn (SupplierInvoice $i): array => ['number' => $i->invoice_number, 'supplier' => $i->supplier->name, 'project' => $i->project->code, 'status' => $i->status,
                    'invoice_date' => $i->invoice_date->toDateString(), 'due_date' => $i->due_date->toDateString(), 'subtotal' => (float) $i->subtotal, 'vat' => (float) $i->vat, 'total' => (float) $i->total]),
            'budget_lines' => $p(BudgetLine::query()->with('project:id,code'))->orderBy('project_id')->orderBy('code')->limit(self::LIMIT)->get()
                ->map(fn (BudgetLine $l): array => ['project' => $l->project->code, ...array_diff_key($this->budgets->figures($l), ['id' => 1])]),
            'suppliers' => Supplier::query()->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Supplier $s): array => ['name' => $s->name, 'type' => $s->type->label(), 'cidb' => $s->cidb_grade ? "{$s->cidb_grade}{$s->cidb_class}" : null, 'bbbee' => $s->bbbee_level,
                    'compliant' => $this->compliance->isCompliant($s) ? 'Yes' : 'No', 'status' => $s->status, 'contact' => $s->contact_name, 'email' => $s->email, 'phone' => $s->phone]),
            'incidents' => $p(SafetyIncident::query()->with('project:id,code'))->orderBy('occurred_at')->limit(self::LIMIT)->get()
                ->map(static fn (SafetyIncident $i): array => ['project' => $i->project->code, 'type' => $i->type->label(), 'date' => $i->occurred_at->toDateString(), 'location' => $i->location,
                    'reportable' => $i->reportable ? 'Yes' : 'No', 'status' => $i->status]),
            'employees' => Employee::query()->orderBy('last_name')->limit(self::LIMIT)->get()
                ->map(static fn (Employee $e): array => ['number' => $e->employee_number, 'name' => $e->name(), 'job' => $e->job_title, 'employment' => str_replace('_', ' ', $e->employment_type),
                    'status' => $e->status, 'start' => $e->start_date->toDateString()]),
            'activities' => $p(ProgrammeActivity::query()->with('project:id,code'))->orderBy('project_id')->orderBy('sort')->limit(self::LIMIT)->get()
                ->map(static fn (ProgrammeActivity $a): array => ['project' => $a->project->code, 'wbs' => $a->wbs, 'name' => $a->name, 'start' => $a->planned_start->toDateString(),
                    'duration' => $a->duration_days, 'percent' => $a->percent_complete]),
            default => collect(),
        };

        /** @var list<array<string, string|int|float|null>> $list */
        $list = array_values($rows->all());

        return $list;
    }

    public function hasProjectFilter(string $dataset): bool
    {
        return in_array($dataset, ['purchase_orders', 'supplier_invoices', 'budget_lines', 'incidents', 'activities'], true);
    }
}
