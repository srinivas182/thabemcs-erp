<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Controllers;

use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Http\Requests\TaskRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TaskController
{
    public function store(TaskRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-projects');

        /** @var User $user */
        $user = $request->user();

        $task = Task::query()->create([...$request->taskData(), 'project_id' => $project->id, 'created_by' => $user->id]);
        $this->notifyAssignee($task, $user);

        return back()->with('success', 'Task added.');
    }

    /**
     * Project managers can change anything; the assignee can update the status of their own task.
     */
    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isAssignee = $task->assignee_id === $user->id;

        if (! $user->can('manage-projects')) {
            abort_unless($isAssignee && array_keys($request->safe()->all()) === ['status'], 403);
        }

        $previousAssignee = $task->assignee_id;
        $data = $request->taskData();

        if (isset($data['status'])) {
            $done = $data['status'] === TaskStatus::Done->value;
            $data['completed_at'] = $done ? ($task->completed_at ?? now()) : null;
        }

        $task->forceFill($data)->save();

        if ($task->assignee_id !== $previousAssignee) {
            $this->notifyAssignee($task, $user);
        }

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    private function notifyAssignee(Task $task, User $by): void
    {
        $assignee = $task->assignee;

        if ($assignee instanceof User && ! $assignee->is($by)) {
            $project = $task->project;
            $assignee->notify(new SystemMessage(
                'New task: '.$task->title,
                trim(($project ? "{$project->name}. " : '').($task->due_date ? 'Due '.$task->due_date->format('j M Y').'.' : '')),
                $project ? route('projects.show', $project) : route('my-day'),
            ));
        }
    }
}
