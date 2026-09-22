<?php

declare(strict_types=1);

namespace App\Domains\Forms\Http\Controllers;

use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormTemplate;
use App\Domains\Forms\Services\FormService;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FormController
{
    public function __construct(private readonly FormService $forms) {}

    public function index(): Response
    {
        Gate::authorize('manage-forms');

        return Inertia::render('forms/index', [
            'templates' => FormTemplate::query()->withCount('submissions')->orderBy('name')->get()->map(static fn (FormTemplate $t): array => [
                'id' => $t->ulid, 'name' => $t->name, 'kind' => $t->kind, 'questions' => count($t->fields), 'active' => $t->active, 'version' => $t->version,
                'submissions' => (int) $t->getAttribute('submissions_count'),
            ]),
        ]);
    }

    public function edit(?FormTemplate $form = null): Response
    {
        Gate::authorize('manage-forms');

        return Inertia::render('forms/builder', [
            'template' => $form ? ['id' => $form->ulid, 'name' => $form->name, 'kind' => $form->kind, 'fields' => $form->fields, 'active' => $form->active, 'version' => $form->version] : null,
            'types' => FormService::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-forms');
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'kind' => ['required', 'in:quality,safety,checklist'], 'fields' => ['required', 'array', 'max:80']]);
        /** @var User $user */
        $user = $request->user();
        FormTemplate::query()->create([
            'name' => $data['name'], 'kind' => $data['kind'], 'fields' => $this->forms->normaliseFields((array) $data['fields']), 'created_by' => $user->id,
        ]);

        return redirect()->route('forms.index')->with('success', 'Form saved. It appears on the site app next time it syncs.');
    }

    public function update(Request $request, FormTemplate $form): RedirectResponse
    {
        Gate::authorize('manage-forms');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'kind' => ['required', 'in:quality,safety,checklist'],
            'fields' => ['required', 'array', 'max:80'], 'active' => ['boolean'],
        ]);
        $fields = $this->forms->normaliseFields((array) $data['fields']);
        // Earlier submissions keep their own copy of the questions, so a new version never changes old answers.
        $form->update([
            'name' => $data['name'], 'kind' => $data['kind'], 'active' => (bool) ($data['active'] ?? true), 'fields' => $fields,
            'version' => $form->fields === $fields ? $form->version : $form->version + 1,
        ]);

        return redirect()->route('forms.index')->with('success', 'Form updated.');
    }

    public function submissions(Project $project): Response
    {
        return Inertia::render('projects/forms', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'submissions' => FormSubmission::query()->with(['template:id,name,kind', 'submitter:id,name'])->where('project_id', $project->id)->latest('submitted_at')->limit(100)->get()
                ->map(static fn (FormSubmission $s): array => [
                    'id' => $s->ulid, 'form' => $s->template->name, 'kind' => $s->template->kind, 'at' => $s->submitted_at->toIso8601String(), 'by' => $s->submitter->name,
                    'passed' => $s->passed, 'fields' => $s->fields, 'answers' => $s->answers,
                ]),
        ]);
    }

    /** Site app: active forms to cache on the phone. */
    public function apiTemplates(): JsonResponse
    {
        Gate::authorize('capture-site');

        return response()->json(['data' => FormTemplate::query()->where('active', true)->orderBy('name')->get()
            ->map(static fn (FormTemplate $t): array => ['id' => $t->ulid, 'name' => $t->name, 'kind' => $t->kind, 'version' => $t->version, 'fields' => $t->fields])]);
    }

    public function apiSubmit(Request $request): JsonResponse
    {
        Gate::authorize('capture-site');
        $data = $request->validate(['projectId' => ['required', 'string'], 'formId' => ['required', 'string'], 'clientId' => ['required', 'uuid'], 'answers' => ['required', 'json']]);
        $project = Project::query()->where('ulid', $data['projectId'])->firstOrFail();
        $template = FormTemplate::query()->where('ulid', $data['formId'])->firstOrFail();

        $photos = [];
        foreach ($template->fields as $field) {
            $file = $request->file('photo_'.$field['id']);
            if ($field['type'] === 'photo' && $file instanceof UploadedFile) {
                $request->validate(['photo_'.$field['id'] => ['image', 'max:10240']]);
                $photos[$field['id']] = $file;
            }
        }

        /** @var User $user */
        $user = $request->user();
        /** @var array<string, mixed> $answers */
        $answers = json_decode((string) $data['answers'], true) ?: [];
        $submission = $this->forms->submit($project, $template, (string) $data['clientId'], $answers, $photos, $user);

        return response()->json(['data' => ['id' => $submission->ulid, 'passed' => $submission->passed]], $submission->wasRecentlyCreated ? 201 : 200);
    }
}
