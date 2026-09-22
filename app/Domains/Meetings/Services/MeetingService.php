<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Enums\TaskPriority;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MeetingService
{
    public const array TYPES = [
        'site' => 'Site meeting', 'progress' => 'Progress meeting', 'design' => 'Design team meeting',
        'safety' => 'Health and safety meeting', 'client' => 'Client meeting', 'other' => 'Other meeting',
    ];

    /**
     * @param  array{type: string, title: string, held_at: string, location?: string|null, attendees?: string|null, apologies?: string|null}  $data
     */
    public function create(Project $project, array $data, User $by): Meeting
    {
        return DB::transaction(fn (): Meeting => Meeting::query()->create([
            ...$data,
            'project_id' => $project->id,
            'number' => (int) Meeting::query()->where('project_id', $project->id)->where('type', $data['type'])->lockForUpdate()->max('number') + 1,
            'status' => 'draft',
            'recorded_by' => $by->id,
        ]));
    }

    /**
     * Action items are project tasks linked to the meeting, so they appear in the owner's My Day.
     */
    public function addAction(Meeting $meeting, string $title, ?User $owner, ?string $due, User $by): Task
    {
        $task = Task::query()->create([
            'project_id' => $meeting->project_id, 'meeting_id' => $meeting->id, 'title' => $title,
            'description' => "Action from {$this->reference($meeting)} ({$meeting->held_at->format('j M Y')})",
            'assignee_id' => $owner?->id, 'due_date' => $due, 'status' => TaskStatus::Open, 'priority' => TaskPriority::Normal, 'created_by' => $by->id,
        ]);

        if ($owner !== null && $meeting->status === 'issued' && ! $owner->is($by)) {
            $owner->notify($this->actionMessage($meeting, $task));
        }

        return $task;
    }

    /**
     * Issuing the minutes locks them and tells everyone with an action.
     */
    public function issue(Meeting $meeting, User $by): void
    {
        $meeting->forceFill(['status' => 'issued', 'issued_at' => now()])->save();

        Task::query()->with('assignee')->where('meeting_id', $meeting->id)->whereNotNull('assignee_id')->get()
            ->each(function (Task $task) use ($meeting, $by): void {
                if ($task->assignee !== null && ! $task->assignee->is($by)) {
                    $task->assignee->notify($this->actionMessage($meeting, $task));
                }
            });
    }

    public function reference(Meeting $meeting): string
    {
        return (self::TYPES[$meeting->type] ?? 'Meeting').' '.$meeting->number;
    }

    private function actionMessage(Meeting $meeting, Task $task): SystemMessage
    {
        return new SystemMessage(
            "Action for you: {$task->title}",
            $this->reference($meeting).($task->due_date ? ', due '.Carbon::parse($task->due_date)->format('j M') : '').'.',
            route('projects.meetings.show', [$meeting->project, $meeting]),
        );
    }
}
