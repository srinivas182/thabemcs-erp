<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Http\Controllers;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Services\MeetingService;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MeetingController
{
    public function __construct(private readonly MeetingService $meetings, private readonly CurrentCompany $context) {}

    public function index(Request $request, Project $project): Response
    {
        return Inertia::render('projects/meetings', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'meetings' => Meeting::query()->withCount(['actions', 'actions as open_actions_count' => fn ($q) => $q->where('status', '!=', TaskStatus::Done->value)])
                ->where('project_id', $project->id)->latest('held_at')->get()
                ->map(fn (Meeting $m): array => [
                    'id' => $m->ulid, 'reference' => $this->meetings->reference($m), 'title' => $m->title, 'at' => $m->held_at->toIso8601String(),
                    'status' => $m->status, 'actions' => (int) $m->getAttribute('actions_count'), 'open' => (int) $m->getAttribute('open_actions_count'),
                ]),
            'types' => collect(MeetingService::TYPES)->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'canManage' => $request->user()?->can('manage-projects') ?? false,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-projects');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(MeetingService::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'held_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:200'],
        ]);
        /** @var User $user */
        $user = $request->user();
        /** @var array{type: string, title: string, held_at: string, location?: string|null} $data */
        $meeting = $this->meetings->create($project, $data, $user);

        return redirect()->route('projects.meetings.show', [$project, $meeting]);
    }

    public function show(Request $request, Project $project, Meeting $meeting): Response
    {
        abort_unless($meeting->project_id === $project->id, 404);

        return Inertia::render('projects/meeting', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'meeting' => [
                'id' => $meeting->ulid, 'reference' => $this->meetings->reference($meeting), 'title' => $meeting->title, 'type' => $meeting->type,
                'at' => $meeting->held_at->toIso8601String(), 'location' => $meeting->location, 'attendees' => $meeting->attendees, 'apologies' => $meeting->apologies,
                'minutes' => $meeting->minutes, 'status' => $meeting->status, 'issuedAt' => $meeting->issued_at?->toIso8601String(), 'by' => $meeting->recorder->name,
            ],
            'actions' => Task::query()->with('assignee:id,name')->where('meeting_id', $meeting->id)->orderBy('id')->get()
                ->map(static fn (Task $t): array => ['id' => $t->ulid, 'title' => $t->title, 'owner' => $t->assignee?->name, 'due' => $t->due_date?->toDateString(), 'status' => $t->status->value]),
            'canManage' => $request->user()?->can('manage-projects') ?? false,
        ]);
    }

    public function update(Request $request, Project $project, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('manage-projects');
        abort_unless($meeting->project_id === $project->id, 404);
        if ($meeting->status === 'issued') {
            return back()->with('error', 'Issued minutes are locked. Record changes in the next meeting.');
        }
        $meeting->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'], 'location' => ['sometimes', 'nullable', 'string', 'max:200'],
            'attendees' => ['sometimes', 'nullable', 'string', 'max:5000'], 'apologies' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'minutes' => ['sometimes', 'nullable', 'string', 'max:100000'],
        ]));

        return back()->with('success', 'Minutes saved.');
    }

    public function action(Request $request, Project $project, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('manage-projects');
        abort_unless($meeting->project_id === $project->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner' => ['nullable', 'string', Rule::exists('users', 'ulid')->where('company_id', $this->context->id())],
            'due_date' => ['nullable', 'date'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $owner = isset($data['owner']) ? User::query()->where('ulid', $data['owner'])->first() : null;
        $this->meetings->addAction($meeting, (string) $data['title'], $owner, $data['due_date'] ?? null, $user);

        return back()->with('success', 'Action added.');
    }

    public function issue(Request $request, Project $project, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('manage-projects');
        abort_unless($meeting->project_id === $project->id, 404);
        /** @var User $user */
        $user = $request->user();
        $this->meetings->issue($meeting, $user);

        return back()->with('success', 'Minutes issued. Everyone with an action has been told.');
    }
}
