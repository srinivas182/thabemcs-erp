<?php

declare(strict_types=1);

namespace App\Domains\Cms\Http\Controllers;

use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsMenu;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPost;
use App\Domains\Cms\Services\CmsException;
use App\Domains\Cms\Services\FormSubmissionService;
use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\SaleUnit;
use App\Support\Cache\CompanyCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The public website. Everything here is read-only apart from the forms, nothing needs a sign-in, and
 * every page is cached so ordinary visitors never touch the database.
 */
final class WebsiteController
{
    public function __construct(private readonly CompanyCache $cache, private readonly FormSubmissionService $forms) {}

    public function home(): View
    {
        $page = CmsPage::query()->where('is_home', true)->where('status', 'published')->first()
            ?? CmsPage::query()->where('status', 'published')->orderBy('id')->firstOrFail();

        return $this->render($page);
    }

    public function page(string $slug): View
    {
        $page = CmsPage::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        abort_unless($page->isLive(), 404);

        return $this->render($page);
    }

    /** Developments come from real projects, so nothing has to be kept up to date by hand. */
    public function developments(): View
    {
        return view('website.developments', [
            ...$this->chrome(),
            'title' => 'Our developments',
            'developments' => $this->cache->remember('website', 'developments', 300, fn (): array => $this->developmentList(20)),
        ]);
    }

    public function development(string $code): View
    {
        $project = Project::query()->where('code', $code)->whereIn('status', ['active', 'on_hold', 'completed'])->firstOrFail();

        $units = SaleUnit::query()->where('project_id', $project->id)
            ->whereIn('tenure', ['sale', 'both'])->whereIn('status', ['available', 'reserved'])
            ->orderBy('reference')->get()
            ->map(static fn (SaleUnit $u): array => [
                'reference' => $u->reference, 'type' => (string) config("sales.unit_types.{$u->type}"),
                'size' => $u->size_m2 === null ? null : (float) $u->size_m2, 'bedrooms' => $u->bedrooms,
                'price' => config('cms.show_prices', true) ? (float) $u->list_price : null,
                'status' => $u->status,
            ])->values()->all();

        return view('website.development', [
            ...$this->chrome(),
            'title' => $project->name,
            'project' => ['name' => $project->name, 'code' => $project->code, 'town' => $project->town,
                'stage' => $project->stage->label(), 'description' => $project->description],
            'units' => $units,
            'available' => count(array_filter($units, static fn (array $u): bool => $u['status'] === 'available')),
            'enquiryForm' => $this->enquiryForm(),
        ]);
    }

    public function articles(): View
    {
        return view('website.articles', [
            ...$this->chrome(),
            'title' => 'News and insight',
            'articles' => CmsPost::query()->with('category:id,name')->where('status', 'published')
                ->orderByDesc('published_at')->limit(30)->get()
                ->map(static fn (CmsPost $p): array => ['title' => $p->title, 'slug' => $p->slug, 'excerpt' => $p->excerpt,
                    'category' => $p->category?->name, 'author' => $p->author_name, 'published' => $p->published_at?->format('j F Y')])->all(),
        ]);
    }

    public function article(string $slug): View
    {
        $post = CmsPost::query()->with('category:id,name')->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('website.article', [
            ...$this->chrome(),
            'title' => $post->title,
            'seo' => ['title' => $post->seo['title'] ?? $post->title, 'description' => $post->seo['description'] ?? $post->excerpt],
            'article' => ['title' => $post->title, 'excerpt' => $post->excerpt, 'blocks' => $post->blocks,
                'category' => $post->category?->name, 'author' => $post->author_name, 'published' => $post->published_at?->format('j F Y')],
        ]);
    }

    /** Someone filled in a form on the website. */
    public function submit(Request $request, string $slug): RedirectResponse
    {
        $form = CmsForm::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        // A field no person can see. Anything that fills it in is a robot, and is quietly ignored.
        if ($request->filled('website_url')) {
            return back()->with('sent', $form->success_message ?? 'Thank you. We will be in touch.');
        }

        try {
            /** @var array<string, mixed> $answers */
            $answers = (array) $request->input('answers', []);
            $this->forms->submit($form, $answers, $request->boolean('consented'),
                $request->ip(), (string) $request->input('page', ''));
        } catch (CmsException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('sent', $form->success_message ?? 'Thank you. We will be in touch shortly.');
    }

    public function sitemap(): HttpResponse
    {
        $urls = CmsPage::query()->where('status', 'published')->where('show_in_search', true)->get()
            ->map(static fn (CmsPage $p): array => ['loc' => url($p->path()), 'changed' => $p->updated_at?->toAtomString()])->all();

        foreach (CmsPost::query()->where('status', 'published')->get() as $post) {
            $urls[] = ['loc' => url("/news/{$post->slug}"), 'changed' => $post->updated_at?->toAtomString()];
        }
        foreach ($this->developmentList(100) as $development) {
            $urls[] = ['loc' => url("/developments/{$development['code']}"), 'changed' => null];
        }

        return response()->view('website.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    /**
     * @return array<string, mixed>
     */
    private function chrome(): array
    {
        return $this->cache->remember('website', 'chrome', 600, function (): array {
            $menus = CmsMenu::query()->get()->keyBy('location');

            return [
                'primaryMenu' => $menus->get('primary')->items ?? [],
                'footerMenu' => $menus->get('footer')->items ?? [],
                'brand' => [
                    'name' => (string) config('branding.name'),
                    'owner' => (string) config('branding.owner'),
                    'tagline' => (string) config('branding.tagline'),
                    'email' => (string) config('branding.support_email'),
                    'phone' => (string) config('branding.support_phone'),
                ],
            ];
        });
    }

    private function render(CmsPage $page): View
    {
        return view('website.page', [
            ...$this->chrome(),
            'title' => $page->title,
            'seo' => ['title' => $page->seo['title'] ?? $page->title, 'description' => $page->seo['description'] ?? null,
                'noindex' => ! $page->show_in_search],
            'blocks' => $page->blocks,
            'developments' => $this->cache->remember('website', 'developments', 300, fn (): array => $this->developmentList(20)),
            'forms' => CmsForm::query()->where('active', true)->get()->keyBy('slug'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function developmentList(int $limit): array
    {
        return Project::query()->whereIn('status', ['active', 'completed'])->orderByDesc('id')->limit($limit)->get()
            ->map(static function (Project $project): array {
                $units = SaleUnit::query()->where('project_id', $project->id)->whereIn('tenure', ['sale', 'both'])->get();

                return [
                    'name' => $project->name, 'code' => $project->code, 'town' => $project->town,
                    'stage' => $project->stage->label(), 'status' => $project->status->value,
                    'description' => $project->description,
                    'units' => $units->count(),
                    'available' => $units->where('status', 'available')->count(),
                    'fromPrice' => config('cms.show_prices', true)
                        ? (float) ($units->where('status', 'available')->min('list_price') ?? 0)
                        : null,
                ];
            })->values()->all();
    }

    private function enquiryForm(): ?CmsForm
    {
        return CmsForm::query()->where('active', true)->where('creates', 'buyer')->orderBy('id')->first();
    }
}
