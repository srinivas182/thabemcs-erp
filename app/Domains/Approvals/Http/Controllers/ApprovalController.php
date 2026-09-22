<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Http\Controllers;

use App\Domains\Approvals\Enums\ApplicationStatus;
use App\Domains\Approvals\Enums\ApplicationType;
use App\Domains\Approvals\Models\StatutoryApplication;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Register of statutory applications across all projects, with expiry and overdue tracking.
 */
final class ApprovalController
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        $project = $request->filled('project') ? Project::query()->where('ulid', $request->string('project')->toString())->first() : null;
        $view = $request->string('view')->toString();

        $applications = StatutoryApplication::query()
            ->with(['project:id,ulid,name,code', 'responsible:id,name'])
            ->when($project, fn ($q) => $q->where('project_id', $project?->id))
            ->when($view === 'attention', fn ($q) => $q->where(function ($w): void {
                $w->where(fn ($e) => $e->where('status', ApplicationStatus::Approved)->whereDate('valid_until', '<=', Carbon::today()->addDays(60)))
                    ->orWhere(fn ($o) => $o->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::Query])->whereDate('expected_decision_on', '<', Carbon::today()));
            }))
            ->orderByRaw("case status when 'query' then 0 when 'submitted' then 1 when 'preparing' then 2 else 3 end")
            ->orderBy('expected_decision_on')
            ->get();

        return Inertia::render('approvals/index', [
            'applications' => $applications->map(static fn (StatutoryApplication $a): array => [
                'id' => $a->ulid,
                'type' => $a->type->value,
                'typeLabel' => $a->type->label(),
                'description' => $a->description,
                'authority' => $a->authority,
                'reference' => $a->reference_number,
                'status' => $a->status->value,
                'project' => ['id' => $a->project->ulid, 'name' => $a->project->name, 'code' => $a->project->code],
                'submittedOn' => $a->submitted_on?->toDateString(),
                'expectedDecisionOn' => $a->expected_decision_on?->toDateString(),
                'decisionOn' => $a->decision_on?->toDateString(),
                'validUntil' => $a->valid_until?->toDateString(),
                'daysToExpiry' => $a->status === ApplicationStatus::Approved ? $a->daysToExpiry() : null,
                'decisionOverdue' => $a->isDecisionOverdue(),
                'conditions' => $a->conditions,
                'responsible' => $a->responsible?->name,
            ]),
            'filters' => ['project' => $project?->ulid, 'view' => $view],
            'projectName' => $project?->name,
            'projects' => Project::query()->orderBy('name')->get(['ulid', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => $p->name])->values(),
            'types' => array_map(static fn (ApplicationType $t): array => ['key' => $t->value, 'label' => $t->label(), 'authority' => $t->typicalAuthority()], ApplicationType::cases()),
            'statuses' => array_map(static fn (ApplicationStatus $s): array => ['key' => $s->value, 'label' => $s->label()], ApplicationStatus::cases()),
            'people' => User::query()->where('company_id', $this->context->id())->where('is_active', true)->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (User $u): array => ['key' => $u->ulid, 'label' => $u->name])->values(),
            'canManage' => $request->user()?->can('manage-projects') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-projects');
        StatutoryApplication::query()->create($this->validated($request, creating: true));

        return back()->with('success', 'Application added to the register.');
    }

    public function update(Request $request, StatutoryApplication $application): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $application->update($this->validated($request, creating: false));

        return back()->with('success', 'Application updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $companyId = $this->context->id();
        $req = $creating ? 'required' : 'sometimes';

        $data = $request->validate([
            'project' => [$req, 'string', Rule::exists('projects', 'ulid')->where('company_id', $companyId)],
            'type' => [$req, Rule::enum(ApplicationType::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:160'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expected_decision_on' => ['nullable', 'date'],
            'decision_on' => ['nullable', 'date', 'before_or_equal:today'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:decision_on'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'responsible' => ['nullable', 'string', Rule::exists('users', 'ulid')->where('company_id', $companyId)],
        ]);

        if (array_key_exists('project', $data)) {
            $data['project_id'] = Project::query()->where('ulid', $data['project'])->value('id');
            unset($data['project']);
        }
        if (array_key_exists('responsible', $data)) {
            $data['responsible_id'] = is_string($data['responsible']) ? User::query()->where('ulid', $data['responsible'])->value('id') : null;
            unset($data['responsible']);
        }

        return $data;
    }
}
