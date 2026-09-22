<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\Models\CustomReport;
use App\Domains\Reporting\Services\DatasetRegistry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ReportDesignerController
{
    public function __construct(private readonly DatasetRegistry $datasets) {}

    public function edit(Request $request, ?CustomReport $report = null): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($report !== null && $report->created_by !== $user->id && ! $user->can('manage-report-schedules'), 403);

        return Inertia::render('reports/designer', [
            'datasets' => collect($this->datasets->definitions())->filter(static fn (array $d): bool => $user->can($d['gate']))
                ->map(static fn (array $d, string $k): array => ['key' => $k, 'label' => $d['label'], 'fields' => collect($d['fields'])->map(static fn (array $f, string $fk): array => ['key' => $fk, ...$f])->values()])->values(),
            'report' => $report ? ['id' => $report->id, 'name' => $report->name, 'dataset' => $report->dataset, 'config' => $report->config] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        /** @var User $user */
        $user = $request->user();
        $report = CustomReport::query()->create([...$data, 'created_by' => $user->id]);

        return redirect()->route('reports.show', 'custom-'.$report->id)->with('success', 'Report saved. You can export or schedule it like any other report.');
    }

    public function update(Request $request, CustomReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($report->created_by !== $user->id && ! $user->can('manage-report-schedules'), 403);
        $report->update($this->validated($request));

        return redirect()->route('reports.show', 'custom-'.$report->id)->with('success', 'Report updated.');
    }

    public function destroy(Request $request, CustomReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($report->created_by !== $user->id && ! $user->can('manage-report-schedules'), 403);
        $report->delete();

        return redirect()->route('reports.index')->with('success', 'Report deleted.');
    }

    /**
     * @return array{name: string, dataset: string, config: array<string, mixed>}
     */
    private function validated(Request $request): array
    {
        $definitions = $this->datasets->definitions();
        $dataset = (string) $request->input('dataset');
        abort_unless(isset($definitions[$dataset]), 422);
        Gate::authorize($definitions[$dataset]['gate']);
        $fields = array_keys($definitions[$dataset]['fields']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'dataset' => ['required', 'string'],
            'columns' => ['required', 'array', 'min:1', 'max:20'],
            'columns.*' => [Rule::in($fields)],
            'filters' => ['nullable', 'array', 'max:10'],
            'filters.*.field' => [Rule::in($fields)],
            'filters.*.op' => ['in:eq,ne,contains,gte,lte'],
            'filters.*.value' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in($fields)],
            'direction' => ['nullable', 'in:asc,desc'],
            'totals' => ['boolean'],
            'subtitle' => ['nullable', 'string', 'max:160'],
        ]);

        return [
            'name' => (string) $data['name'], 'dataset' => $dataset,
            'config' => [
                'columns' => array_values(array_unique($data['columns'])),
                'filters' => array_values(array_map(static fn (array $f): array => ['field' => $f['field'], 'op' => $f['op'], 'value' => (string) ($f['value'] ?? '')], $data['filters'] ?? [])),
                'sort' => $data['sort'] ?? null, 'direction' => $data['direction'] ?? 'asc', 'totals' => (bool) ($data['totals'] ?? true), 'subtitle' => $data['subtitle'] ?? null,
            ],
        ];
    }
}
