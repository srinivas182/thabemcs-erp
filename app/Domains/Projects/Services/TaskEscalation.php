<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Task;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\IteratesCompanies;
use Illuminate\Support\Carbon;

/**
 * Daily: tasks more than two working days overdue are escalated once to the project manager.
 */
final class TaskEscalation
{
    use IteratesCompanies;

    public function __construct(private readonly CurrentCompany $context) {}

    public function run(?Company $only = null): int
    {
        $count = 0;
        $this->companies($only)->each(function (Company $company) use (&$count): void {
            $this->context->runFor($company, function () use (&$count): void {
                Task::query()->with(['project', 'assignee'])
                    ->whereNotIn('status', [TaskStatus::Done->value])
                    ->whereNull('escalated_at')
                    ->whereDate('due_date', '<', Carbon::today('Africa/Johannesburg')->subWeekdays(2)->toDateString())
                    ->get()
                    ->each(function (Task $task) use (&$count): void {
                        $manager = $task->project?->projectManager;
                        if ($manager !== null && $manager->id !== $task->assignee_id) {
                            $manager->notify(new SystemMessage(
                                "Overdue task: {$task->title}",
                                ($task->assignee ? "Assigned to {$task->assignee->name}, " : 'Not assigned, ').'due '.$task->due_date?->format('j M').'.',
                                route('projects.show', $task->project),
                                'warning',
                            ));
                            $count++;
                        }
                        $task->forceFill(['escalated_at' => now()])->save();
                    });
            });
        });

        return $count;
    }
}
