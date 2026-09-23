<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Services;

use App\Domains\Closeout\Models\CloseoutItem;
use App\Domains\Closeout\Models\Distribution;
use App\Domains\Finance\Services\ProfitabilityService;
use App\Domains\Funding\Services\FundingSummary;
use App\Domains\Platform\Services\WebhookDispatcher;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closing a development out: the checklist of everything that must be in place, and the final account
 * against the approved feasibility.
 */
final class CloseoutService
{
    public function __construct(
        private readonly ProfitabilityService $profitability,
        private readonly FundingSummary $funding,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * Create the checklist for a project the first time it is opened, from configuration.
     *
     * @return Collection<int, CloseoutItem>
     */
    public function items(Project $project)
    {
        if (! CloseoutItem::query()->where('project_id', $project->id)->exists()) {
            /** @var list<string> $optional */
            $optional = (array) config('closeout.optional');
            $sort = 0;
            DB::transaction(function () use ($project, $optional, &$sort): void {
                /** @var array<string, array<string, string>> $groups */
                $groups = (array) config('closeout.items');
                foreach ($groups as $group => $items) {
                    foreach ($items as $key => $label) {
                        CloseoutItem::query()->create([
                            'project_id' => $project->id, 'key' => $key, 'label' => $label, 'group' => $group,
                            'required' => ! in_array($key, $optional, true), 'sort' => $sort++,
                        ]);
                    }
                }
            });
        }

        return CloseoutItem::query()->where('project_id', $project->id)->orderBy('sort')->get();
    }

    public function complete(CloseoutItem $item, ?Carbon $on, ?string $notes, User $by): void
    {
        $item->update([
            'completed_on' => ($on ?? Carbon::today())->toDateString(),
            'completed_by' => $by->id,
            'notes' => $notes,
        ]);
    }

    public function reopen(CloseoutItem $item): void
    {
        $item->update(['completed_on' => null, 'completed_by' => null]);
    }

    /**
     * The final account: what the project actually cost and earned against the approved feasibility.
     *
     * @return array<string, mixed>
     */
    public function finalAccount(Project $project): array
    {
        $profit = $this->profitability->forProject($project);
        $funding = $this->funding->forProject($project);
        $distributed = (float) Distribution::query()->where('project_id', $project->id)->where('status', 'paid')->sum('amount');

        return [
            'baseline' => $profit['baseline'],
            'final' => $profit['forecast'],
            'revenueSource' => $profit['revenueSource'],
            'sales' => $profit['sales'],
            'costToDate' => $profit['costToDate'],
            'funding' => $funding,
            'distributed' => round($distributed, 2),
            'undistributed' => round($profit['forecast']['profit'] - $distributed, 2),
        ];
    }

    /**
     * A project may only be marked complete once every required item is done.
     *
     * @return array{done: int, required: int, outstanding: list<string>}
     */
    public function readiness(Project $project): array
    {
        $items = $this->items($project);
        $required = $items->where('required', true);
        $outstanding = $required->whereNull('completed_on');

        return [
            'done' => $items->whereNotNull('completed_on')->count(),
            'required' => $required->count(),
            'outstanding' => array_values($outstanding->pluck('label')->all()),
        ];
    }

    public function closeProject(Project $project, User $by): void
    {
        $readiness = $this->readiness($project);
        if ($readiness['outstanding'] !== []) {
            throw new RuntimeException('These items are still outstanding: '.implode('; ', array_slice($readiness['outstanding'], 0, 5)).'.');
        }

        $project->update(['status' => ProjectStatus::Completed]);
        activity('closeout')->causedBy($by)->performedOn($project)->log('Project closed out');
        $this->webhooks->send('project.completed', ['project' => $project->code, 'name' => $project->name, 'closedOn' => now()->toDateString()]);
    }
}
