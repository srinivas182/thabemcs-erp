<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Exceptions\StageGateBlockedException;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Models\StageTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A project can only move to the next stage when every required checklist item
 * for its current stage is complete, and an approver signs it off.
 */
final class StageGateService
{
    public function setComplete(StageGateItem $item, bool $complete, User $by, ?string $notes = null): void
    {
        $item->forceFill([
            'completed_at' => $complete ? now() : null,
            'completed_by' => $complete ? $by->id : null,
            'notes' => $notes ?? $item->notes,
        ])->save();
    }

    /**
     * Required items still open for the project's current stage.
     *
     * @return list<string>
     */
    public function blockers(Project $project): array
    {
        $titles = StageGateItem::query()
            ->where('project_id', $project->id)
            ->where('stage', $project->stage)
            ->where('is_required', true)
            ->whereNull('completed_at')
            ->orderBy('sort')
            ->pluck('title')
            ->all();

        return array_values(array_map(strval(...), $titles));
    }

    /**
     * @throws StageGateBlockedException
     */
    public function advance(Project $project, User $approver, ?string $comment = null): StageTransition
    {
        $next = $project->stage->next() ?? throw new StageGateBlockedException('This project is already at the final stage.');

        if ($this->blockers($project) !== []) {
            throw new StageGateBlockedException('Complete all required checklist items before moving to the next stage.');
        }

        $transition = DB::transaction(function () use ($project, $approver, $comment, $next): StageTransition {
            $transition = StageTransition::query()->create([
                'project_id' => $project->id,
                'from_stage' => $project->stage,
                'to_stage' => $next,
                'approved_by' => $approver->id,
                'comment' => $comment,
            ]);

            $project->update(['stage' => $next]);

            return $transition;
        });

        $manager = $project->projectManager;
        if ($manager instanceof User && ! $manager->is($approver)) {
            $manager->notify(new SystemMessage(
                "{$project->name} moved to {$next->label()}",
                "{$approver->name} approved the {$transition->from_stage->label()} stage gate.",
                route('projects.show', $project),
            ));
        }

        return $transition;
    }
}
