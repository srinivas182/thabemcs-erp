<?php

declare(strict_types=1);

namespace App\Domains\Cms\Http\Controllers;

use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPageVersion;
use App\Domains\Cms\Services\CmsException;
use App\Domains\Cms\Services\PageService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page editor in the back office. What is edited here is what the public website shows.
 */
final class PageController
{
    public function __construct(private readonly PageService $pages) {}

    public function index(): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('cms/pages', [
            'pages' => CmsPage::query()->withCount('versions')->orderByDesc('is_home')->orderBy('title')->get()
                ->map(static fn (CmsPage $p): array => [
                    'id' => $p->ulid, 'title' => $p->title, 'slug' => $p->slug, 'path' => $p->path(),
                    'template' => $p->template, 'status' => $p->status, 'isHome' => $p->is_home,
                    'live' => $p->isLive(), 'publishFrom' => $p->publish_from?->toIso8601String(),
                    'published' => $p->published_at?->toIso8601String(), 'updated' => $p->updated_at?->toIso8601String(),
                    'versions' => (int) $p->getAttribute('versions_count'),
                ]),
            'templates' => collect((array) config('cms.templates'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
        ]);
    }

    public function edit(CmsPage $page): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('cms/editor', [
            'page' => [
                'id' => $page->ulid, 'title' => $page->title, 'slug' => $page->slug, 'path' => $page->path(),
                'template' => $page->template, 'blocks' => $page->blocks, 'seo' => $page->seo ?? [],
                'status' => $page->status, 'isHome' => $page->is_home, 'showInSearch' => $page->show_in_search,
                'version' => $page->version, 'live' => $page->isLive(),
            ],
            'blockTypes' => collect($this->blockTypes())->map(static fn (array $b, string $k): array => [
                'key' => $k, 'label' => $b['label'], 'help' => $b['help'] ?? null,
                'fields' => collect($b['fields'])->map(static fn (array $f, string $name): array => ['name' => $name, ...$f])->values(),
            ])->values(),
            'templates' => collect((array) config('cms.templates'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
            'media' => CmsMedia::query()->latest('id')->limit(60)->get()
                ->map(static fn (CmsMedia $m): array => ['id' => $m->ulid, 'url' => $m->url(), 'alt' => $m->alt, 'name' => $m->file_name])->values(),
            'forms' => CmsForm::query()->where('active', true)->orderBy('name')->get()
                ->map(static fn (CmsForm $f): array => ['key' => $f->slug, 'label' => $f->name])->values(),
            'versions' => $page->versions()->with('savedBy:id,name')->limit(20)->get()
                ->map(static fn (CmsPageVersion $v): array => ['id' => $v->id, 'version' => $v->version, 'note' => $v->note,
                    'at' => $v->created_at?->toIso8601String()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'template' => ['required', Rule::in(array_keys((array) config('cms.templates')))],
        ]);
        /** @var User $user */
        $user = $request->user();
        $page = $this->pages->create([...$data, 'blocks' => []], $user);

        return redirect()->route('cms.pages.edit', $page)->with('success', 'Page created. Add some blocks, then publish it.');
    }

    public function update(Request $request, CmsPage $page): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160'],
            'template' => ['required', Rule::in(array_keys((array) config('cms.templates')))],
            'blocks' => ['present', 'array', 'max:40'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:180'],
            'seo.image' => ['nullable', 'string', 'max:300'],
            'show_in_search' => ['boolean'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->pages->update($page, $data, $user, $data['note'] ?? null);

        return back()->with('success', 'Saved. '.($page->isLive() ? 'The website has been updated.' : 'This page is still a draft.'));
    }

    public function publish(Request $request, CmsPage $page): RedirectResponse
    {
        Gate::authorize('publish-content');
        $data = $request->validate(['publish_from' => ['nullable', 'date', 'after:now']]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->pages->publish($page, $user, isset($data['publish_from']) ? Carbon::parse((string) $data['publish_from']) : null);
        } catch (CmsException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', isset($data['publish_from'])
            ? 'Scheduled. It appears on the website at the time you set.'
            : 'Published. It is on the website now.');
    }

    public function unpublish(Request $request, CmsPage $page): RedirectResponse
    {
        Gate::authorize('publish-content');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->pages->unpublish($page, $user);
        } catch (CmsException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Taken off the website. It is a draft again.');
    }

    public function makeHome(Request $request, CmsPage $page): RedirectResponse
    {
        Gate::authorize('publish-content');
        /** @var User $user */
        $user = $request->user();
        $this->pages->makeHome($page, $user);

        return back()->with('success', "\"{$page->title}\" is now the home page.");
    }

    public function restore(Request $request, CmsPage $page, CmsPageVersion $version): RedirectResponse
    {
        Gate::authorize('manage-content');
        abort_if($version->cms_page_id !== $page->id, 404);
        /** @var User $user */
        $user = $request->user();
        $this->pages->restore($page, $version, $user);

        return back()->with('success', "Put back to version {$version->version}.");
    }

    public function destroy(CmsPage $page): RedirectResponse
    {
        Gate::authorize('publish-content');
        if ($page->is_home) {
            return back()->with('error', 'The home page cannot be deleted.');
        }
        $page->delete();

        return redirect()->route('cms.pages')->with('success', 'Page deleted.');
    }

    /**
     * @return array<string, array{label: string, help?: string, fields: array<string, array<string, mixed>>}>
     */
    private function blockTypes(): array
    {
        /** @var array<string, array{label: string, help?: string, fields: array<string, array<string, mixed>>}> $blocks */
        $blocks = (array) config('cms.blocks');

        return $blocks;
    }
}
