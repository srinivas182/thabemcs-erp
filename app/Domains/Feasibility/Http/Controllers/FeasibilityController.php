<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Http\Controllers;

use App\Domains\Feasibility\Enums\LineBasis;
use App\Domains\Feasibility\Enums\LineCategory;
use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Models\FeasibilityLine;
use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class FeasibilityController
{
    public function __construct(private readonly FeasibilityService $service) {}

    public function show(Request $request, Project $project): Response
    {
        $scenarios = Feasibility::query()->where('project_id', $project->id)->orderByDesc('is_baseline')->orderBy('name')->get();
        $selected = $scenarios->firstWhere('ulid', $request->string('scenario')->toString())
            ?? $scenarios->firstWhere('is_baseline', true)
            ?? $scenarios->first();

        /** @var User $user */
        $user = $request->user();
        $selected?->load(['lines', 'approver']);

        return Inertia::render('projects/feasibility', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'scenarios' => $scenarios->map(static fn (Feasibility $f): array => [
                'id' => $f->ulid, 'name' => $f->name, 'isBaseline' => $f->is_baseline, 'status' => $f->status->value,
            ]),
            'scenario' => $selected ? [
                'id' => $selected->ulid,
                'name' => $selected->name,
                'durationMonths' => $selected->duration_months,
                'units' => $selected->units,
                'status' => $selected->status->value,
                'isBaseline' => $selected->is_baseline,
                'approvedBy' => $selected->approver?->name,
                'approvedAt' => $selected->approved_at?->toIso8601String(),
                'lines' => $selected->lines->map(static fn (FeasibilityLine $l): array => [
                    'category' => $l->category->value,
                    'description' => $l->description,
                    'basis' => $l->basis->value,
                    'amount' => $l->amount,
                    'rate' => $l->rate,
                    'start_month' => $l->start_month,
                    'end_month' => $l->end_month,
                ])->values(),
                'results' => $this->service->results($selected),
            ] : null,
            'categories' => array_map(static fn (LineCategory $c): array => ['key' => $c->value, 'label' => $c->label()], LineCategory::cases()),
            'bases' => array_map(static fn (LineBasis $b): array => ['key' => $b->value, 'label' => $b->label()], LineBasis::cases()),
            'can' => ['edit' => $user->can('manage-feasibility'), 'approve' => $user->can('approve-stage-gate')],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-feasibility');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'duration_months' => ['required', 'integer', 'between:6,240'],
            'units' => ['nullable', 'integer', 'min:1'],
            'copy_from' => ['nullable', 'string'],
        ]);

        $copyFrom = isset($data['copy_from'])
            ? Feasibility::query()->with('lines')->where('project_id', $project->id)->where('ulid', $data['copy_from'])->first()
            : null;

        $feasibility = $this->service->create(
            $project,
            (string) $data['name'],
            (int) $data['duration_months'],
            isset($data['units']) ? (int) $data['units'] : null,
            $copyFrom,
        );

        return redirect()->route('projects.feasibility', [$project, 'scenario' => $feasibility->ulid])->with('success', "Scenario \"{$feasibility->name}\" created.");
    }

    public function update(Request $request, Feasibility $feasibility): RedirectResponse
    {
        Gate::authorize('manage-feasibility');

        if ($feasibility->isApproved()) {
            return back()->with('error', 'Approved scenarios are locked. Copy it to a new scenario to make changes.');
        }

        $data = $request->validate([
            'duration_months' => ['required', 'integer', 'between:6,240'],
            'units' => ['nullable', 'integer', 'min:1'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.category' => ['required', Rule::enum(LineCategory::class)],
            'lines.*.description' => ['required', 'string', 'max:160'],
            'lines.*.basis' => ['required', Rule::enum(LineBasis::class)],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.start_month' => ['required', 'integer', 'min:1', 'lte:duration_months'],
            'lines.*.end_month' => ['required', 'integer', 'gte:lines.*.start_month', 'lte:duration_months'],
        ], [
            'lines.*.end_month.gte' => 'The end month must be on or after the start month.',
            'lines.*.start_month.lte' => 'Months must fall within the project duration.',
            'lines.*.end_month.lte' => 'Months must fall within the project duration.',
        ]);

        /** @var list<array{category: string, description: string, basis: string, amount: float|string|null, rate: float|string|null, start_month: int, end_month: int}> $lines */
        $lines = array_values($data['lines']);
        $this->service->saveLines($feasibility, $lines, (int) $data['duration_months'], isset($data['units']) ? (int) $data['units'] : null);

        return back()->with('success', 'Feasibility saved.');
    }

    public function approve(Request $request, Feasibility $feasibility): RedirectResponse
    {
        Gate::authorize('approve-stage-gate');

        /** @var User $user */
        $user = $request->user();
        $this->service->approve($feasibility, $user);

        return back()->with('success', "\"{$feasibility->name}\" approved as the project baseline.");
    }
}
