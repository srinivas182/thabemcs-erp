<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Controllers;

use App\Domains\Projects\Http\Requests\RiskRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class RiskController
{
    public function store(RiskRequest $request, Project $project): RedirectResponse
    {
        Risk::query()->create([...$request->riskData(), 'project_id' => $project->id]);

        return back()->with('success', $request->string('kind')->toString() === 'issue' ? 'Issue logged.' : 'Risk added.');
    }

    public function update(RiskRequest $request, Risk $risk): RedirectResponse
    {
        $risk->update($request->riskData());

        return back();
    }

    public function destroy(Risk $risk): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $risk->delete();

        return back()->with('success', 'Removed from the register.');
    }
}
