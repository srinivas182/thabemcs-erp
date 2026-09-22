<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Day" — the personal home page every user lands on after signing in.
 *
 * Phase 1 shows the portfolio across the development cycle. Approvals, tasks and
 * alerts are wired in as each module is delivered.
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

        return Inertia::render('my-day', [
            'greetingName' => str($user->name)->before(' ')->toString(),
            'pipeline' => $pipeline,
            'approvals' => [],
            'tasks' => [],
            'alerts' => [],
        ]);
    }
}
