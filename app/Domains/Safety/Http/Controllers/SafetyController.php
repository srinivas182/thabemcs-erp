<?php

declare(strict_types=1);

namespace App\Domains\Safety\Http\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Enums\IncidentType;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Safety\Models\ToolboxTalk;
use App\Domains\Site\Models\Inspection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Health and safety per project: incidents and near misses, safety inspections, toolbox talks.
 */
final class SafetyController
{
    public function show(Request $request, Project $project): Response
    {
        $incidents = SafetyIncident::query()->with('reporter:id,name')->where('project_id', $project->id)->orderByDesc('occurred_at')->get();
        $since = now()->subDays(90);

        return Inertia::render('projects/safety', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'stats' => [
                'daysSinceLostTime' => ($last = $incidents->first(static fn (SafetyIncident $i): bool => in_array($i->type, [IncidentType::LostTime, IncidentType::Fatality], true)))
                    ? (int) $last->occurred_at->diffInDays(now()) : null,
                'nearMisses90' => $incidents->filter(static fn (SafetyIncident $i): bool => $i->type === IncidentType::NearMiss && $i->occurred_at->greaterThan($since))->count(),
                'open' => $incidents->where('status', '!=', 'closed')->count(),
                'talks90' => ToolboxTalk::query()->where('project_id', $project->id)->whereDate('held_on', '>=', $since)->count(),
            ],
            'incidents' => $incidents->map(static fn (SafetyIncident $i): array => [
                'id' => $i->ulid, 'type' => $i->type->value, 'typeLabel' => $i->type->label(), 'at' => $i->occurred_at->toIso8601String(),
                'location' => $i->location, 'description' => $i->description, 'immediateAction' => $i->immediate_action,
                'person' => $i->person_involved, 'reportable' => $i->reportable, 'reviewReportable' => $i->type->needsReportabilityReview(),
                'reportedAt' => $i->reported_to_authority_at?->toIso8601String(), 'rootCause' => $i->root_cause,
                'correctiveAction' => $i->corrective_action, 'status' => $i->status, 'by' => $i->reporter?->name,
            ]),
            'inspections' => Inspection::query()->where('project_id', $project->id)->where('kind', 'safety')->orderByDesc('inspected_on')->limit(30)->get()
                ->map(static fn (Inspection $i): array => ['id' => $i->ulid, 'title' => $i->title, 'location' => $i->location, 'result' => $i->result, 'findings' => $i->findings, 'on' => $i->inspected_on->toDateString()]),
            'talks' => ToolboxTalk::query()->where('project_id', $project->id)->orderByDesc('held_on')->limit(30)->get()
                ->map(static fn (ToolboxTalk $t): array => ['id' => $t->id, 'topic' => $t->topic, 'on' => $t->held_on->toDateString(), 'attendees' => $t->attendees, 'presenter' => $t->presenter]),
            'canManage' => $request->user()?->can('manage-safety') ?? false,
        ]);
    }

    /**
     * Investigation: root cause, corrective action, reporting to the authority, closing.
     */
    public function updateIncident(Request $request, SafetyIncident $incident): RedirectResponse
    {
        Gate::authorize('manage-safety');
        $data = $request->validate([
            'reportable' => ['sometimes', 'boolean'],
            'reported_to_authority_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
            'root_cause' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'corrective_action' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:open,investigating,closed'],
        ]);

        $closing = ($data['status'] ?? null) === 'closed';
        $rootCause = $data['root_cause'] ?? $incident->root_cause;
        $action = $data['corrective_action'] ?? $incident->corrective_action;

        if ($closing && $incident->type !== IncidentType::NearMiss && (! $rootCause || ! $action)) {
            return back()->with('error', 'Record the root cause and corrective action before closing this incident.');
        }
        if ($closing && ($data['reportable'] ?? $incident->reportable) && ! ($data['reported_to_authority_at'] ?? $incident->reported_to_authority_at)) {
            return back()->with('error', 'This incident is reportable. Record when it was reported to the Department of Employment and Labour before closing it.');
        }

        $incident->update($data);

        return back()->with('success', 'Incident updated.');
    }

    public function storeTalk(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-safety');
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:200'],
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'attendees' => ['required', 'integer', 'between:1,2000'],
            'presenter' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        ToolboxTalk::query()->create([...$data, 'project_id' => $project->id, 'recorded_by' => $user->id]);

        return back()->with('success', 'Toolbox talk recorded.');
    }
}
