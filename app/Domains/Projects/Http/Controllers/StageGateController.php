<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Controllers;

use App\Domains\Projects\Exceptions\StageGateBlockedException;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Services\StageGateService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StageGateController
{
    public function __construct(private readonly StageGateService $gates) {}

    /**
     * Tick or untick a checklist item. Only the current stage's items can change;
     * earlier stages are a locked record.
     */
    public function toggle(Request $request, Project $project, StageGateItem $item): RedirectResponse
    {
        Gate::authorize('manage-projects');
        abort_unless($item->project_id === $project->id, 404);

        if ($item->stage !== $project->stage) {
            return back()->with('error', 'Only the current stage\'s checklist can be changed.');
        }

        $validated = $request->validate(['complete' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000']]);

        /** @var User $user */
        $user = $request->user();
        $this->gates->setComplete($item, (bool) $validated['complete'], $user, isset($validated['notes']) ? (string) $validated['notes'] : null);

        return back();
    }

    public function advance(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('approve-stage-gate');
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        /** @var User $user */
        $user = $request->user();

        try {
            $transition = $this->gates->advance($project, $user, isset($validated['comment']) ? (string) $validated['comment'] : null);
        } catch (StageGateBlockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Approved. {$project->name} is now in {$transition->to_stage->label()}.");
    }
}
