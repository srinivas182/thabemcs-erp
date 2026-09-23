<?php

declare(strict_types=1);

namespace App\Domains\Documents\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\DocumentVersion;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Services\QuotaService;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly CurrentCompany $context,
        private readonly QuotaService $quotas,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $project = $request->filled('project') ? Project::query()->where('ulid', $request->string('project')->toString())->first() : null;
        $folder = $request->string('folder')->toString();
        $category = DocumentCategory::tryFrom($request->string('category')->toString());
        $term = trim($request->string('q')->toString());

        $base = Document::query()->where('project_id', $project?->id);

        $folders = (clone $base)->selectRaw('folder, count(*) as total')->groupBy('folder')->orderBy('folder')->pluck('total', 'folder');

        $documents = (clone $base)
            ->with(['versions.uploader:id,name'])
            ->when($folder !== '', fn ($q) => $q->where('folder', $folder))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('drawing_number', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderBy('folder')->orderBy('title')
            ->limit(200)
            ->get()
            ->filter(fn (Document $d): bool => $d->isVisibleTo($user))
            ->map(static fn (Document $d): array => [
                'id' => $d->ulid,
                'title' => $d->title,
                'folder' => $d->folder,
                'category' => $d->category->value,
                'categoryLabel' => $d->category->label(),
                'drawingNumber' => $d->drawing_number,
                'drawingDiscipline' => $d->drawing_discipline,
                'restricted' => $d->restricted_to_roles !== null && $d->restricted_to_roles !== [],
                'versions' => $d->versions->map(static fn (DocumentVersion $v): array => [
                    'id' => $v->id, 'version' => $v->version, 'revision' => $v->revision, 'name' => $v->original_name,
                    'size' => $v->size_bytes, 'by' => $v->uploader?->name, 'at' => $v->created_at->toIso8601String(), 'notes' => $v->notes,
                ])->values(),
            ])->values();

        $company = $this->context->get();

        return Inertia::render('documents/index', [
            'project' => $project ? ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code] : null,
            'folders' => $folders,
            'documents' => $documents,
            'filters' => ['folder' => $folder, 'category' => $category?->value, 'q' => $term],
            'categories' => array_map(static fn (DocumentCategory $c): array => ['key' => $c->value, 'label' => $c->label()], DocumentCategory::cases()),
            'roles' => array_map(static fn (Role $r): array => ['key' => $r->value, 'label' => $r->label()], Role::cases()),
            'storage' => $company ? ['used' => $this->quotas->usage($company, QuotaType::StorageMb), 'limit' => $this->quotas->limit($company, QuotaType::StorageMb)] : null,
            'accept' => '.'.implode(',.', (array) config('platform.upload_mimes')),
            'canUpload' => $user->can('manage-documents'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-documents');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', (array) config('platform.upload_mimes')), 'max:'.config('platform.upload_max_kb')],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'folder' => ['required', 'string', 'max:190', 'regex:/^[\pL\pN _\-&().,\/]+$/u', 'not_regex:/\.\./'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'drawing_number' => ['nullable', 'required_if:category,drawing', 'string', 'max:60'],
            'drawing_discipline' => ['nullable', 'string', 'max:40'],
            'revision' => ['nullable', 'string', 'max:16'],
            'restricted_to_roles' => ['nullable', 'array'],
            'restricted_to_roles.*' => [Rule::enum(Role::class)],
        ], [
            'folder.regex' => 'Folder names may use letters, numbers, spaces and - _ & ( ) , . /',
            'folder.not_regex' => 'Folder names cannot contain "..".',
        ]);

        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');

        try {
            $this->documents->upload($file, [
                'project_id' => isset($data['project']) ? Project::query()->where('ulid', $data['project'])->value('id') : null,
                'folder' => trim((string) $data['folder'], '/ '),
                'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'category' => $data['category'],
                'drawing_number' => $data['drawing_number'] ?? null,
                'drawing_discipline' => $data['drawing_discipline'] ?? null,
                'restricted_to_roles' => ($data['restricted_to_roles'] ?? []) ?: null,
            ], $user, $data['revision'] ?? null);
        } catch (QuotaExceededException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Document uploaded.');
    }

    public function addVersion(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('manage-documents');
        $this->ensureVisible($request, $document);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', (array) config('platform.upload_mimes')), 'max:'.config('platform.upload_max_kb')],
            'revision' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $version = $this->documents->addVersion($document, $request->file('file'), $user, $data['revision'] ?? null, $data['notes'] ?? null);
        } catch (QuotaExceededException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Version {$version->version} uploaded.");
    }

    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document_id === $document->id, 404);
        $this->ensureVisible($request, $document);

        activity('documents')->causedBy($request->user())->performedOn($document)
            ->withProperties(['version' => $version->version])->log('Document downloaded');

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('manage-documents');
        $this->ensureVisible($request, $document);
        $document->delete();

        return back()->with('success', 'Document archived. It can be restored by an administrator.');
    }

    /**
     * Restricted documents return 404 so their existence is not revealed.
     */
    private function ensureVisible(Request $request, Document $document): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($document->isVisibleTo($user), 404);
    }
}
