<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Http\Controllers;

use App\Domains\Closeout\Models\CloseoutItem;
use App\Domains\Closeout\Services\CloseoutService;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class CloseoutController
{
    public function __construct(private readonly CloseoutService $closeout) {}

    public function show(Project $project): Response
    {
        Gate::authorize('view-financial-reports');
        /** @var array<string, string> $groupLabels */
        $groupLabels = ['construction' => 'Construction', 'statutory' => 'Statutory and compliance', 'handover' => 'Handover', 'financial' => 'Financial', 'records' => 'Records'];

        return Inertia::render('projects/closeout', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code, 'status' => $project->status->value],
            'items' => $this->closeout->items($project)->map(static fn (CloseoutItem $i): array => [
                'id' => $i->id, 'key' => $i->key, 'label' => $i->label, 'group' => $i->group, 'groupLabel' => $groupLabels[$i->group] ?? $i->group,
                'required' => $i->required, 'completed' => $i->completed_on?->toDateString(), 'notes' => $i->notes,
            ])->values(),
            'readiness' => $this->closeout->readiness($project),
            'finalAccount' => $this->closeout->finalAccount($project),
            'canManage' => Gate::allows('manage-projects'),
            'canClose' => Gate::allows('close-projects'),
        ]);
    }

    public function complete(Request $request, CloseoutItem $item): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $data = $request->validate(['completed_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */
        $user = $request->user();
        $this->closeout->complete($item, isset($data['completed_on']) ? Carbon::parse((string) $data['completed_on']) : null, $data['notes'] ?? null, $user);

        return back()->with('success', "{$item->label}: done.");
    }

    public function reopen(CloseoutItem $item): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $this->closeout->reopen($item);

        return back()->with('success', "{$item->label} reopened.");
    }

    public function close(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('close-projects');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->closeout->closeProject($project, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$project->name} is closed out and marked complete.");
    }
}
