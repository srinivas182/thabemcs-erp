<?php

declare(strict_types=1);

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Services\CashFlowService;
use App\Domains\Finance\Services\ExportService;
use App\Domains\Projects\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ReportsExportController
{
    public function __construct(private readonly ExportService $exports, private readonly CashFlowService $cashflow) {}

    public function cashflow(Project $project): InertiaResponse
    {
        return Inertia::render('projects/cashflow', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            ...$this->cashflow->forProject($project),
        ]);
    }

    public function index(): InertiaResponse
    {
        Gate::authorize('manage-finance');

        return Inertia::render('finance/exports');
    }

    public function download(Request $request, string $type): Response
    {
        Gate::authorize('manage-finance');
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse((string) $data['from']);
        $to = Carbon::parse((string) $data['to']);

        $content = match ($type) {
            'supplier-invoices' => $this->exports->supplierInvoices($from, $to),
            'payments' => $this->exports->payments($from, $to),
            'payroll-inputs' => $this->exports->payrollInputs($from, $to),
            default => abort(404),
        };

        activity('finance')->causedBy($request->user())->withProperties(['type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()])->log('Export downloaded');

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => sprintf('attachment; filename="%s-%s-to-%s.csv"', $type, $from->format('Ymd'), $to->format('Ymd')),
        ]);
    }
}
