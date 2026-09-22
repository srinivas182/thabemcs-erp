<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Controllers;

use App\Domains\Projects\Http\Requests\MilestoneRequest;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class MilestoneController
{
    public function store(MilestoneRequest $request, Project $project): RedirectResponse
    {
        Milestone::query()->create([...$request->validated(), 'project_id' => $project->id]);

        return back()->with('success', 'Milestone added.');
    }

    public function update(MilestoneRequest $request, Milestone $milestone): RedirectResponse
    {
        $milestone->update($request->validated());

        return back();
    }

    public function destroy(Milestone $milestone): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $milestone->delete();

        return back()->with('success', 'Milestone removed.');
    }
}
