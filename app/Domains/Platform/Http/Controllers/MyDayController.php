<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Approvals\Enums\ApplicationStatus;
use App\Domains\Approvals\Models\StatutoryApplication;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Enums\RiskStatus;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Models\Task;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Day": the personal home page. Shows the portfolio across the development cycle,
 * the user's open tasks, stage gates waiting for their approval, and alerts.
 */
final class MyDayController
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $counts = Project::query()
            ->where('status', ProjectStatus::Active)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $pipeline = array_map(static fn (ProjectStage $stage): array => [
            'key' => $stage->value,
            'label' => $stage->label(),
            'count' => (int) ($counts[$stage->value] ?? 0),
        ], ProjectStage::cases());

        $tasks = Task::query()
            ->with('project')
            ->where('assignee_id', $user->id)
            ->where('status', '!=', TaskStatus::Done)
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(static fn (Task $t): array => [
                'id' => $t->ulid,
                'title' => $t->title,
                'project' => $t->project?->name,
                'projectId' => $t->project?->ulid,
                'dueDate' => $t->due_date?->toDateString(),
                'overdue' => $t->isOverdue(),
            ]);

        return Inertia::render('my-day', [
            'greetingName' => str($user->name)->before(' ')->toString(),
            'pipeline' => $pipeline,
            'tasks' => $tasks,
            'approvals' => [
                ...app(ApprovalEngine::class)->pendingFor($user)->map(static function (ApprovalRequest $r): array {
                    /** @var Model&Approvable $item */
                    $item = $r->approvable;

                    return [
                        'title' => $item->approvalTitle(),
                        'detail' => 'R'.number_format((float) $r->amount, 0, '.', ' ')." excl. VAT, from {$r->requester->name}.",
                        'url' => route('inbox'),
                    ];
                })->all(),
                ...($user->can('approve-stage-gate') ? $this->gatesReadyForApproval() : []),
            ],
            'alerts' => $this->alerts($user),
        ]);
    }

    /**
     * Active projects whose current-stage checklist is complete, waiting for sign-off.
     *
     * @return list<array{title: string, detail: string, url: string}>
     */
    private function gatesReadyForApproval(): array
    {
        $blocked = StageGateItem::query()
            ->join('projects', 'projects.id', '=', 'stage_gate_items.project_id')
            ->whereColumn('stage_gate_items.stage', 'projects.stage')
            ->where('stage_gate_items.is_required', true)
            ->whereNull('stage_gate_items.completed_at')
            ->distinct()
            ->pluck('stage_gate_items.project_id');

        return array_values(Project::query()
            ->where('status', ProjectStatus::Active)
            ->where('stage', '!=', ProjectStage::Close)
            ->whereNotIn('id', $blocked)
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(static fn (Project $p): array => [
                'title' => "{$p->name}: {$p->stage->label()} gate",
                'detail' => 'Checklist complete. Approve to move to '.($p->stage->next()?->label() ?? 'the next stage').'.',
                'url' => route('projects.show', $p),
            ])
            ->all());
    }

    /**
     * @return list<array{title: string, detail: string, url: string|null, level: string}>
     */
    private function alerts(User $user): array
    {
        $alerts = [];

        $overdue = Task::query()->where('assignee_id', $user->id)->where('status', '!=', TaskStatus::Done)
            ->whereDate('due_date', '<', Carbon::today())->count();
        if ($overdue > 0) {
            $alerts[] = ['title' => $overdue === 1 ? '1 overdue task' : "{$overdue} overdue tasks", 'detail' => 'Past their due date and not done yet.', 'url' => null, 'level' => 'danger'];
        }

        Risk::query()->with('project:id,ulid,name')->where('owner_id', $user->id)->where('status', '!=', RiskStatus::Closed)
            ->whereRaw('likelihood * impact >= 10')->limit(5)->get()
            ->each(function (Risk $risk) use (&$alerts): void {
                $alerts[] = [
                    'title' => ucfirst($risk->rating())." {$risk->kind->value}: {$risk->title}",
                    'detail' => "You own this on {$risk->project?->name}.",
                    'url' => $risk->project ? route('projects.show', $risk->project) : null,
                    'level' => 'warning',
                ];
            });

        if ($user->can('manage-suppliers')) {
            $expiring = SupplierDocument::query()->whereBetween('expires_on', [Carbon::today(), Carbon::today()->addDays(30)])->count();
            $expired = SupplierDocument::query()->whereDate('expires_on', '<', Carbon::today())
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('supplier_documents as newer')
                    ->whereColumn('newer.supplier_id', 'supplier_documents.supplier_id')
                    ->whereColumn('newer.type', 'supplier_documents.type')
                    ->whereColumn('newer.id', '>', 'supplier_documents.id'))
                ->count();
            if ($expired + $expiring > 0) {
                $alerts[] = [
                    'title' => trim(($expired ? "{$expired} supplier documents expired" : '').($expired && $expiring ? ', ' : '').($expiring ? "{$expiring} expire within 30 days" : '')),
                    'detail' => 'Expired documents block appointments and payments.',
                    'url' => route('suppliers.index'),
                    'level' => $expired ? 'danger' : 'warning',
                ];
            }
        }

        if ($user->can('manage-projects')) {
            StatutoryApplication::query()->with('project:id,ulid,name')
                ->where('status', ApplicationStatus::Approved)
                ->whereDate('valid_until', '<=', Carbon::today()->addDays(60))
                ->orderBy('valid_until')->limit(5)->get()
                ->each(function (StatutoryApplication $a) use (&$alerts): void {
                    $days = $a->daysToExpiry() ?? 0;
                    $alerts[] = [
                        'title' => $a->type->label().($days < 0 ? ' has lapsed' : " lapses in {$days} days"),
                        'detail' => "{$a->project->name}. Start construction or apply for an extension before it lapses.",
                        'url' => route('approvals.index', ['project' => $a->project->ulid]),
                        'level' => $days < 14 ? 'danger' : 'warning',
                    ];
                });

            $overdue = StatutoryApplication::query()
                ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::Query])
                ->whereDate('expected_decision_on', '<', Carbon::today())->count();
            if ($overdue > 0) {
                $alerts[] = [
                    'title' => $overdue === 1 ? '1 application decision is overdue' : "{$overdue} application decisions are overdue",
                    'detail' => 'Follow up with the authority.',
                    'url' => route('approvals.index', ['view' => 'attention']),
                    'level' => 'warning',
                ];
            }
        }

        return $alerts;
    }
}
