<?php

declare(strict_types=1);

namespace App\Domains\Programme\Http\Controllers;

use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class ProgrammeController
{
    public function __construct(private readonly ScheduleService $schedule, private readonly CurrentCompany $context) {}

    public function show(Request $request, Project $project): Response
    {
        $activities = ProgrammeActivity::query()->with(['owner:id,name', 'supplier:id,name', 'predecessors.predecessor:id,ulid,name'])
            ->where('project_id', $project->id)->orderBy('sort')->orderBy('id')->get();
        $plan = $this->schedule->calculate($project);

        return Inertia::render('projects/programme', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'finish' => $plan['finish'],
            'plannedCompletion' => $project->planned_completion_date?->toDateString(),
            'activities' => $activities->map(static fn (ProgrammeActivity $a): array => [
                'id' => $a->ulid, 'wbs' => $a->wbs, 'name' => $a->name, 'plannedStart' => $a->planned_start->toDateString(),
                'duration' => $a->duration_days, 'actualStart' => $a->actual_start?->toDateString(), 'actualFinish' => $a->actual_finish?->toDateString(),
                'percent' => $a->percent_complete, 'owner' => $a->owner?->name, 'supplier' => $a->supplier?->name,
                ...($plan['activities'][$a->id] ?? ['earlyStart' => $a->planned_start->toDateString(), 'earlyFinish' => $a->planned_start->toDateString(), 'float' => 0, 'critical' => false, 'behind' => false]),
                'predecessors' => $a->predecessors->map(static fn (ActivityDependency $d): array => ['id' => $d->id, 'activity' => $d->predecessor->ulid, 'name' => $d->predecessor->name, 'lag' => $d->lag_days])->values(),
            ])->values(),
            'people' => User::query()->where('company_id', $this->context->id())->where('is_active', true)->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (User $u): array => ['key' => $u->ulid, 'label' => $u->name])->values(),
            'suppliers' => Supplier::query()->whereIn('type', ['contractor', 'subcontractor'])->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (Supplier $s): array => ['key' => $s->ulid, 'label' => $s->name])->values(),
            'canManage' => $request->user()?->can('manage-projects') ?? false,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $data = $this->validated($request);
        ProgrammeActivity::query()->create([
            ...$data, 'project_id' => $project->id,
            'sort' => (int) ProgrammeActivity::query()->where('project_id', $project->id)->max('sort') + 1,
        ]);

        return back()->with('success', 'Activity added.');
    }

    public function update(Request $request, ProgrammeActivity $activity): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $data = $this->validated($request, partial: true);
        if (isset($data['percent_complete']) && (int) $data['percent_complete'] > 0 && $activity->actual_start === null && ! isset($data['actual_start'])) {
            $data['actual_start'] = now('Africa/Johannesburg')->toDateString();
        }
        if (isset($data['percent_complete']) && (int) $data['percent_complete'] === 100 && $activity->actual_finish === null && ! isset($data['actual_finish'])) {
            $data['actual_finish'] = now('Africa/Johannesburg')->toDateString();
        }
        $activity->update($data);

        return back()->with('success', 'Activity updated.');
    }

    public function destroy(ProgrammeActivity $activity): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $activity->delete();

        return back()->with('success', 'Activity removed.');
    }

    public function link(Request $request, ProgrammeActivity $activity): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $data = $request->validate(['predecessor' => ['required', 'string'], 'lag_days' => ['nullable', 'integer', 'between:-60,365']]);
        $predecessor = ProgrammeActivity::query()->where('ulid', $data['predecessor'])->where('project_id', $activity->project_id)->firstOrFail();

        try {
            $this->schedule->assertNoCycle($predecessor, $activity);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityDependency::query()->updateOrCreate(
            ['predecessor_id' => $predecessor->id, 'successor_id' => $activity->id],
            ['lag_days' => (int) ($data['lag_days'] ?? 0)],
        );

        return back()->with('success', "{$activity->name} now follows {$predecessor->name}.");
    }

    public function unlink(ActivityDependency $dependency): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $dependency->delete();

        return back()->with('success', 'Link removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'wbs' => ['sometimes', 'nullable', 'string', 'max:20'],
            'name' => [$req, 'string', 'max:200'],
            'planned_start' => [$req, 'date'],
            'duration_days' => [$req, 'integer', 'between:0,2000'],
            'actual_start' => ['sometimes', 'nullable', 'date'],
            'actual_finish' => ['sometimes', 'nullable', 'date', 'after_or_equal:actual_start'],
            'percent_complete' => ['sometimes', 'integer', 'between:0,100'],
            'owner' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'ulid')->where('company_id', $this->context->id())],
            'supplier' => ['sometimes', 'nullable', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'budget_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('owner', $data)) {
            $data['owner_id'] = $data['owner'] ? User::query()->where('ulid', $data['owner'])->value('id') : null;
            unset($data['owner']);
        }
        if (array_key_exists('supplier', $data)) {
            $data['supplier_id'] = $data['supplier'] ? Supplier::query()->where('ulid', $data['supplier'])->value('id') : null;
            unset($data['supplier']);
        }

        return $data;
    }
}
