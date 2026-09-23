<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleUnit;

/**
 * Stock and sales position: every unit, its price, who bought it and where the transfer has reached.
 */
final class SalesReport implements Report
{
    public function key(): string
    {
        return 'sales-schedule';
    }

    public function title(): string
    {
        return 'Sales schedule';
    }

    public function description(): string
    {
        return 'Every unit with its price, status, buyer and transfer progress. Amounts exclude VAT.';
    }

    public function gate(): string
    {
        return 'view-sales';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $units = SaleUnit::query()->with('project:id,code')
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->orderBy('project_id')->orderBy('reference')->get();

        $agreements = SaleAgreement::query()->with(['buyer:id,name', 'steps'])
            ->whereIn('sale_unit_id', $units->modelKeys())->whereIn('status', ['conditional', 'unconditional', 'registered'])
            ->get()->keyBy('sale_unit_id');

        $rows = $units->map(static function (SaleUnit $unit) use ($agreements): array {
            $agreement = $agreements->get($unit->id);
            $stage = $agreement === null ? null : $agreement->steps->whereNotNull('completed_on')->last()?->step;

            return [
                'project' => $unit->project->code, 'unit' => $unit->reference, 'type' => (string) config("sales.unit_types.{$unit->type}"),
                'status' => ucfirst($unit->status), 'price' => $unit->netPrice(),
                'sold' => $agreement === null ? null : $agreement->netPrice(),
                'buyer' => $agreement?->buyer->name, 'signed' => $agreement?->signed_on->toDateString(),
                'stage' => $stage === null ? null : (string) config("sales.transfer_steps.{$stage}"),
                'registered' => $agreement?->registered_on?->toDateString(),
            ];
        })->values()->all();

        $sold = array_values(array_filter($rows, static fn (array $r): bool => $r['sold'] !== null));

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'text'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ['key' => 'price', 'label' => 'List price', 'type' => 'money'], ['key' => 'sold', 'label' => 'Sold for', 'type' => 'money'],
            ['key' => 'buyer', 'label' => 'Buyer', 'type' => 'text'], ['key' => 'signed', 'label' => 'Signed', 'type' => 'date'],
            ['key' => 'stage', 'label' => 'Transfer reached', 'type' => 'text'], ['key' => 'registered', 'label' => 'Registered', 'type' => 'date'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['price']), ...ReportResult::sum($sold, ['sold'])],
            count($sold).' of '.count($rows).' units sold.');
    }
}
