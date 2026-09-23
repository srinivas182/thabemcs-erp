<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Domains\Platform\Models\ImportRun;
use App\Domains\Platform\Services\ImportService;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ImportController
{
    public function __construct(private readonly ImportService $imports) {}

    public function index(): Response
    {
        Gate::authorize('manage-company');

        return Inertia::render('settings/import', [
            'types' => collect($this->imports->types())->map(static fn (array $d, string $key): array => [
                'key' => $key, 'label' => $d['label'], 'needsProject' => (bool) ($d['project'] ?? false),
                'columns' => collect($d['columns'])->map(static fn (array $c, string $name): array => ['name' => $name, 'label' => $c['label'], 'required' => $c['required']])->values(),
            ])->values(),
            'runs' => ImportRun::query()->latest('id')->limit(20)->get()->map(static fn (ImportRun $r): array => [
                'id' => $r->ulid, 'type' => $r->type, 'file' => $r->file_name, 'read' => $r->rows_read,
                'imported' => $r->rows_imported, 'status' => $r->status, 'errors' => $r->errors, 'at' => $r->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function template(string $type): HttpResponse
    {
        Gate::authorize('manage-company');

        return response($this->imports->template($type), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$type}-template.csv\"",
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        Gate::authorize('manage-company');
        $data = $request->validate([
            'type' => [Rule::in(array_keys($this->imports->types()))],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')],
            'file' => ['required', 'file', 'max:5120'],
            'confirm' => ['boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        $contents = (string) file_get_contents($file->getRealPath());
        $project = isset($data['project']) ? Project::query()->where('ulid', $data['project'])->first() : null;

        $checked = $this->imports->check((string) $data['type'], $contents, $project);
        if (! ($data['confirm'] ?? false) || $checked['errors'] !== []) {
            ImportRun::query()->create([
                'type' => $data['type'], 'file_name' => $file->getClientOriginalName(), 'rows_read' => $checked['rows'],
                'rows_imported' => 0, 'errors' => $checked['errors'], 'status' => $checked['errors'] === [] ? 'checked' : 'failed', 'created_by' => $user->id,
            ]);

            return back()->with($checked['errors'] === [] ? 'success' : 'error', $checked['errors'] === []
                ? "{$checked['rows']} rows checked and all are fine. Tick \"import them\" and upload again to load them."
                : count($checked['errors']).' rows have problems, so nothing was imported. Fix them and upload again.');
        }

        $result = $this->imports->import((string) $data['type'], $contents, $project, $user);
        ImportRun::query()->create([
            'type' => $data['type'], 'file_name' => $file->getClientOriginalName(), 'rows_read' => $checked['rows'],
            'rows_imported' => $result['imported'], 'errors' => $result['errors'], 'status' => $result['errors'] === [] ? 'imported' : 'failed', 'created_by' => $user->id,
        ]);

        return back()->with('success', "{$result['imported']} rows imported.");
    }
}
