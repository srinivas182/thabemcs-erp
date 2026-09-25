<?php

declare(strict_types=1);

namespace App\Domains\Cms\Http\Controllers;

use App\Domains\Cms\Models\CmsCategory;
use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsFormSubmission;
use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Cms\Models\CmsMenu;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPost;
use App\Domains\Cms\Services\CmsException;
use App\Domains\Cms\Services\MediaService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rest of the content management: media, menus, the blog, forms and what people send through them.
 */
final class CmsController
{
    public function __construct(private readonly MediaService $media) {}

    public function mediaIndex(): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('cms/media', [
            'media' => CmsMedia::query()->with('uploader:id,name')->latest('id')->paginate(48)
                ->through(static fn (CmsMedia $m): array => [
                    'id' => $m->ulid, 'url' => $m->url(), 'name' => $m->file_name, 'alt' => $m->alt,
                    'size' => round($m->bytes / 1024).' KB', 'dimensions' => $m->width ? "{$m->width} x {$m->height}" : null,
                    'uploaded' => $m->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function mediaStore(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'alt' => ['nullable', 'string', 'max:190'],
        ]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->media->upload($request->file('file'), $request->string('alt')->toString() ?: null, $user);
        } catch (CmsException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Uploaded.');
    }

    public function mediaUpdate(Request $request, CmsMedia $media): RedirectResponse
    {
        Gate::authorize('manage-content');
        $media->update($request->validate([
            'alt' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:190'],
        ]));

        return back()->with('success', 'Saved.');
    }

    public function mediaDestroy(CmsMedia $media): RedirectResponse
    {
        Gate::authorize('manage-content');
        $this->media->delete($media);

        return back()->with('success', 'Deleted. Check any page that used it.');
    }

    public function menus(): Response
    {
        Gate::authorize('manage-content');
        $menus = CmsMenu::query()->get()->keyBy('location');

        return Inertia::render('cms/menus', [
            'menus' => [
                'primary' => $menus->get('primary')->items ?? [],
                'footer' => $menus->get('footer')->items ?? [],
            ],
            'pages' => CmsPage::query()->where('status', 'published')->orderBy('title')->get()
                ->map(static fn (CmsPage $p): array => ['key' => $p->path(), 'label' => $p->title])->values(),
        ]);
    }

    public function saveMenu(Request $request, string $location): RedirectResponse
    {
        Gate::authorize('manage-content');
        abort_unless(in_array($location, ['primary', 'footer'], true), 404);
        $data = $request->validate([
            'items' => ['present', 'array', 'max:20'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.link' => ['required', 'string', 'max:200'],
            'items.*.children' => ['nullable', 'array', 'max:12'],
            'items.*.children.*.label' => ['required', 'string', 'max:60'],
            'items.*.children.*.link' => ['required', 'string', 'max:200'],
        ]);
        CmsMenu::query()->updateOrCreate(['location' => $location], ['items' => array_values($data['items'])]);

        return back()->with('success', 'Menu saved.');
    }

    public function posts(): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('cms/posts', [
            'posts' => CmsPost::query()->with('category:id,name')->latest('id')->paginate(25)
                ->through(static fn (CmsPost $p): array => [
                    'id' => $p->ulid, 'title' => $p->title, 'slug' => $p->slug, 'excerpt' => $p->excerpt,
                    'category' => $p->category?->name, 'author' => $p->author_name, 'status' => $p->status,
                    'published' => $p->published_at?->toIso8601String(),
                ]),
            'categories' => CmsCategory::query()->orderBy('name')->get()
                ->map(static fn (CmsCategory $c): array => ['key' => (string) $c->id, 'label' => $c->name])->values(),
        ]);
    }

    public function storePost(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:50000'],
            'category' => ['nullable', 'integer'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'publish' => ['boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $slug = Str::slug((string) $data['title']);
        $n = 2;
        while (CmsPost::query()->where('slug', $slug)->exists()) {
            $slug = Str::slug((string) $data['title'])."-{$n}";
            $n++;
        }

        CmsPost::query()->create([
            'title' => $data['title'], 'slug' => $slug, 'excerpt' => $data['excerpt'] ?? null,
            'blocks' => [['type' => 'intro', 'data' => ['body' => $data['body']]]],
            'cms_category_id' => $data['category'] ?? null, 'author_name' => $data['author_name'] ?? $user->name,
            'status' => ($data['publish'] ?? false) ? 'published' : 'draft',
            'published_at' => ($data['publish'] ?? false) ? now() : null,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Article saved.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        CmsCategory::query()->create(['name' => $data['name'], 'slug' => Str::slug((string) $data['name'])]);

        return back()->with('success', 'Category added.');
    }

    public function forms(): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('cms/forms', [
            'forms' => CmsForm::query()->withCount('submissions')->orderBy('name')->get()
                ->map(static fn (CmsForm $f): array => [
                    'id' => $f->ulid, 'name' => $f->name, 'slug' => $f->slug, 'fields' => $f->fields,
                    'creates' => $f->creates, 'active' => $f->active, 'recipients' => $f->recipients ?? [],
                    'successMessage' => $f->success_message, 'submissions' => (int) $f->getAttribute('submissions_count'),
                ]),
            'fieldTypes' => config('cms.form_fields'),
        ]);
    }

    public function storeForm(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'fields' => ['required', 'array', 'min:1', 'max:20'],
            'fields.*.name' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:80'],
            'fields.*.type' => [Rule::in((array) config('cms.form_fields'))],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:20'],
            'creates' => ['required', 'in:none,buyer,tenant'],
            'recipients' => ['nullable', 'array', 'max:10'],
            'recipients.*' => ['string'],
            'success_message' => ['nullable', 'string', 'max:300'],
        ], ['fields.*.name.regex' => 'Field names must be lowercase letters, numbers and underscores.']);
        /** @var User $user */
        $user = $request->user();

        CmsForm::query()->create([
            'name' => $data['name'], 'slug' => Str::slug((string) $data['name']), 'fields' => array_values($data['fields']),
            'creates' => $data['creates'], 'recipients' => $data['recipients'] ?? [], 'success_message' => $data['success_message'] ?? null,
            'active' => true, 'created_by' => $user->id,
        ]);

        return back()->with('success', 'Form created. Add it to a page with the form block.');
    }

    public function submissions(Request $request): Response
    {
        Gate::authorize('manage-content');
        $status = $request->string('status')->toString() ?: 'new';

        return Inertia::render('cms/submissions', [
            'submissions' => CmsFormSubmission::query()->with('form:id,name')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)
                ->through(static function (CmsFormSubmission $s): array {
                    $form = $s->form;

                    return [
                        'id' => $s->ulid, 'form' => $form !== null ? $form->name : 'Form', 'name' => $s->name, 'email' => $s->email, 'phone' => $s->phone,
                        'answers' => $s->answers, 'page' => $s->page, 'status' => $s->status,
                        'becameBuyer' => $s->buyer_id !== null, 'becameTenant' => $s->tenant_id !== null,
                        'at' => $s->created_at?->toIso8601String(),
                    ];
                }),
            'filters' => ['status' => $status],
        ]);
    }

    public function updateSubmission(Request $request, CmsFormSubmission $submission): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['status' => ['required', 'in:new,actioned,spam']]);
        /** @var User $user */
        $user = $request->user();
        $submission->forceFill(['status' => $data['status'], 'handled_by' => $user->id])->save();

        return back()->with('success', 'Updated.');
    }
}
