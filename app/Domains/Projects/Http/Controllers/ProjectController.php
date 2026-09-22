<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Controllers;

use App\Domains\Platform\Enums\Province;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Models\Region;
use App\Domains\Projects\Enums\DevelopmentType;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Enums\RiskStatus;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Http\Requests\ProjectRequest;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Models\StageTransition;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\StageGateService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly StageGateService $gates,
        private readonly CurrentCompany $context,
    ) {}

    public function index(Request $request): Response
    {
        $stage = ProjectStage::tryFrom($request->string('stage')->toString());
        $status = ProjectStatus::tryFrom($request->string('status')->toString()) ?? ProjectStatus::Active;
        $term = trim($request->string('q')->toString());

        $query = Project::query()
            ->with('projectManager')
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', TaskStatus::Done)])
            ->where('status', $status)
            ->when($stage, fn ($q) => $q->where('stage', $stage))
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('town', 'like', $like));
            })
            ->orderBy('name');

        $projects = $query->paginate(25)->withQueryString()->through(fn (Project $p): array => [
            'id' => $p->ulid,
            'code' => $p->code,
            'name' => $p->name,
            'type' => $p->development_type?->label(),
            'stage' => ['key' => $p->stage->value, 'label' => $p->stage->label()],
            'location' => trim(implode(', ', array_filter([$p->town, $p->province?->label()])), ', '),
            'value' => $p->estimated_value,
            'manager' => $p->projectManager?->name,
            'openTasks' => (int) $p->getAttribute('open_tasks_count'),
        ]);

        $counts = Project::query()->where('status', $status)->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => ['stage' => $stage?->value, 'status' => $status->value, 'q' => $term],
            'stages' => array_map(static fn (ProjectStage $s): array => ['key' => $s->value, 'label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0)], ProjectStage::cases()),
            'statuses' => array_map(static fn (ProjectStatus $s): string => $s->value, ProjectStatus::cases()),
            'canCreate' => $request->user()?->can('manage-projects') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-projects');

        return Inertia::render('projects/form', ['project' => null, 'suggestedCode' => $this->projects->nextCode(), ...$this->formOptions()]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $project = $this->projects->create($request->projectData(), $user);
        } catch (QuotaExceededException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('projects.show', $project)->with('success', "{$project->name} created. Work through the Plan checklist to move it forward.");
    }

    public function show(Request $request, Project $project): Response
    {
        /** @var User $user */
        $user = $request->user();
        $project->load(['projectManager', 'region']);

        $gateItems = $project->gateItems()->with('completer')->get()->groupBy(fn (StageGateItem $i): string => $i->stage->value);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->ulid,
                'code' => $project->code,
                'name' => $project->name,
                'type' => $project->development_type?->label(),
                'stage' => $project->stage->value,
                'status' => $project->status->value,
                'province' => $project->province?->label(),
                'town' => $project->town,
                'region' => $project->region?->name,
                'value' => $project->estimated_value,
                'plannedStart' => $project->planned_start_date?->toDateString(),
                'plannedCompletion' => $project->planned_completion_date?->toDateString(),
                'description' => $project->getAttribute('description'),
                'manager' => $project->projectManager?->name,
            ],
            'stages' => array_map(static fn (ProjectStage $s): array => [
                'key' => $s->value,
                'label' => $s->label(),
                'items' => ($gateItems[$s->value] ?? collect())->map(static fn (StageGateItem $i): array => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'required' => $i->is_required,
                    'completedAt' => $i->completed_at?->toIso8601String(),
                    'completedBy' => $i->completer?->name,
                ])->values(),
            ], ProjectStage::cases()),
            'blockers' => $this->gates->blockers($project),
            'transitions' => $project->transitions()->with('approver')->get()->map(static fn (StageTransition $t): array => [
                'from' => $t->from_stage->label(),
                'to' => $t->to_stage->label(),
                'by' => $t->approver?->name,
                'comment' => $t->comment,
                'at' => $t->created_at->toIso8601String(),
            ]),
            'milestones' => $project->milestones()->get()->map(static fn (Milestone $m): array => [
                'id' => $m->id,
                'title' => $m->title,
                'stage' => $m->stage?->label(),
                'plannedDate' => $m->planned_date->toDateString(),
                'forecastDate' => $m->forecast_date?->toDateString(),
                'completedOn' => $m->completed_on?->toDateString(),
                'daysLate' => $m->daysLate(),
            ]),
            'tasks' => $project->tasks()->with('assignee')->orderByRaw("case when status = 'done' then 1 else 0 end")->orderBy('due_date')->get()->map(static fn (Task $t): array => [
                'id' => $t->ulid,
                'title' => $t->title,
                'assignee' => $t->assignee?->name,
                'assigneeId' => $t->assignee?->ulid,
                'dueDate' => $t->due_date?->toDateString(),
                'status' => $t->status->value,
                'priority' => $t->priority->value,
                'overdue' => $t->isOverdue(),
            ]),
            'risks' => $project->risks()->with('owner')->orderByRaw("case when status = 'closed' then 1 else 0 end")->orderByDesc('likelihood')->orderByDesc('impact')->get()->map(static fn (Risk $r): array => [
                'id' => $r->ulid,
                'kind' => $r->kind->value,
                'title' => $r->title,
                'likelihood' => $r->likelihood,
                'impact' => $r->impact,
                'score' => $r->score(),
                'rating' => $r->rating(),
                'mitigation' => $r->mitigation,
                'owner' => $r->owner?->name,
                'status' => $r->status->value,
                'reviewDate' => $r->review_date?->toDateString(),
            ]),
            'openRiskCount' => $project->risks()->where('status', '!=', RiskStatus::Closed)->count(),
            'people' => $this->people(),
            'can' => [
                'manage' => $user->can('manage-projects'),
                'approve' => $user->can('approve-stage-gate'),
            ],
        ]);
    }

    public function edit(Project $project): Response
    {
        Gate::authorize('manage-projects');

        return Inertia::render('projects/form', [
            'project' => [
                'id' => $project->ulid,
                'code' => $project->code,
                'name' => $project->name,
                'development_type' => $project->development_type?->value,
                'status' => $project->status->value,
                'region_id' => $project->region_id,
                'province' => $project->province?->value,
                'town' => $project->town,
                'estimated_value' => $project->estimated_value,
                'planned_start_date' => $project->planned_start_date?->toDateString(),
                'planned_completion_date' => $project->planned_completion_date?->toDateString(),
                'description' => $project->getAttribute('description'),
                'project_manager' => $project->projectManager?->ulid,
            ],
            'suggestedCode' => null,
            ...$this->formOptions(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->projectData());

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'developmentTypes' => array_map(static fn (DevelopmentType $t): array => ['key' => $t->value, 'label' => $t->label()], DevelopmentType::cases()),
            'provinces' => array_map(static fn (Province $p): array => ['key' => $p->value, 'label' => $p->label()], Province::cases()),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name'])->map(static fn (Region $r): array => ['key' => $r->id, 'label' => $r->name]),
            'people' => $this->people(),
            'statuses' => array_map(static fn (ProjectStatus $s): string => $s->value, ProjectStatus::cases()),
        ];
    }

    /**
     * Active people in the company, for assigning managers, tasks and risk owners.
     *
     * @return list<array{key: string, label: string}>
     */
    private function people(): array
    {
        return array_values(User::query()
            ->where('company_id', $this->context->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['ulid', 'name', 'job_title'])
            ->map(static fn (User $u): array => ['key' => $u->ulid, 'label' => $u->job_title ? "{$u->name} ({$u->job_title})" : $u->name])
            ->all());
    }
}
