<?php

declare(strict_types=1);

namespace App\Domains\Cms\Services;

use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPageVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pages on the public website.
 *
 * Every save keeps the previous content as a version, so a change can always be undone. A page is only
 * visible to the public once it is published, and can be set to appear on a date.
 */
final class PageService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $by): CmsPage
    {
        return DB::transaction(function () use ($data, $by): CmsPage {
            $page = CmsPage::query()->create([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['title'])),
                'template' => $data['template'] ?? 'page',
                'blocks' => $this->clean($data['blocks'] ?? []),
                'seo' => $data['seo'] ?? null,
                'status' => 'draft',
                'show_in_search' => (bool) ($data['show_in_search'] ?? true),
                'version' => 1,
                'created_by' => $by->id,
            ]);
            $this->keepVersion($page, $by, 'Created');

            return $page;
        });
    }

    /**
     * Save a change. The content as it was is kept as a version first.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPage $page, array $data, User $by, ?string $note = null): CmsPage
    {
        return DB::transaction(function () use ($page, $data, $by, $note): CmsPage {
            $this->keepVersion($page, $by, $note);

            $page->update([
                'title' => $data['title'] ?? $page->title,
                'slug' => isset($data['slug']) && $data['slug'] !== $page->slug
                    ? $this->uniqueSlug((string) $data['slug'], $page->id)
                    : $page->slug,
                'template' => $data['template'] ?? $page->template,
                'blocks' => isset($data['blocks']) ? $this->clean($data['blocks']) : $page->blocks,
                'seo' => $data['seo'] ?? $page->seo,
                'show_in_search' => (bool) ($data['show_in_search'] ?? $page->show_in_search),
                'version' => $page->version + 1,
                'updated_by' => $by->id,
            ]);

            return $page->fresh() ?? $page;
        });
    }

    public function publish(CmsPage $page, User $by, ?Carbon $from = null): void
    {
        if ($page->blocks === []) {
            throw new CmsException('Add some content before publishing this page.');
        }

        $page->update([
            'status' => 'published',
            'published_at' => $page->published_at ?? now(),
            'publish_from' => $from?->toDateTimeString(),
            'updated_by' => $by->id,
        ]);

        activity('cms')->causedBy($by)->performedOn($page)->log($from !== null ? 'Page scheduled to publish' : 'Page published');
    }

    public function unpublish(CmsPage $page, User $by): void
    {
        if ($page->is_home) {
            throw new CmsException('The home page cannot be taken down. Publish a different page as the home page first.');
        }

        $page->update(['status' => 'draft', 'updated_by' => $by->id]);
        activity('cms')->causedBy($by)->performedOn($page)->log('Page taken off the website');
    }

    /** Only one page is the home page. */
    public function makeHome(CmsPage $page, User $by): void
    {
        DB::transaction(function () use ($page, $by): void {
            CmsPage::query()->where('is_home', true)->update(['is_home' => false]);
            $page->update(['is_home' => true, 'status' => 'published', 'published_at' => $page->published_at ?? now(), 'updated_by' => $by->id]);
            activity('cms')->causedBy($by)->performedOn($page)->log('Page set as the home page');
        });
    }

    /** Put the page back to how it was at a given version. The current content is kept first. */
    public function restore(CmsPage $page, CmsPageVersion $version, User $by): CmsPage
    {
        return DB::transaction(function () use ($page, $version, $by): CmsPage {
            $this->keepVersion($page, $by, "Before going back to version {$version->version}");

            $page->update([
                'title' => $version->title,
                'blocks' => $version->blocks,
                'seo' => $version->seo,
                'version' => $page->version + 1,
                'updated_by' => $by->id,
            ]);
            activity('cms')->causedBy($by)->performedOn($page)->log("Page restored to version {$version->version}");

            return $page->fresh() ?? $page;
        });
    }

    private function keepVersion(CmsPage $page, User $by, ?string $note): void
    {
        CmsPageVersion::query()->create([
            'cms_page_id' => $page->id, 'version' => $page->version, 'title' => $page->title,
            'blocks' => $page->blocks, 'seo' => $page->seo, 'note' => $note, 'saved_by' => $by->id,
        ]);

        // Keep the last twenty versions of a page; older ones are of no practical use.
        $old = CmsPageVersion::query()->where('cms_page_id', $page->id)->orderByDesc('version')->skip(20)->take(50)->pluck('id');
        if ($old->isNotEmpty()) {
            CmsPageVersion::query()->whereIn('id', $old)->delete();
        }
    }

    /**
     * A web address for the page that is not already taken, and not one the application itself uses.
     */
    public function uniqueSlug(string $from, ?int $ignoreId = null): string
    {
        $base = Str::slug($from) ?: 'page';
        /** @var list<string> $reserved */
        $reserved = (array) config('cms.reserved_slugs');
        if (in_array($base, $reserved, true)) {
            $base .= '-page';
        }

        $slug = $base;
        $n = 2;
        while (CmsPage::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /**
     * Keep only known block types and known fields, so nothing unexpected reaches the website.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function clean(array $blocks): array
    {
        /** @var array<string, array{fields: array<string, array<string, mixed>>}> $known */
        $known = (array) config('cms.blocks');
        $clean = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['type']) || ! isset($known[$block['type']])) {
                continue;
            }
            $fields = $known[$block['type']]['fields'];
            /** @var array<string, mixed> $given */
            $given = is_array($block['data'] ?? null) ? $block['data'] : [];
            $clean[] = ['type' => (string) $block['type'], 'data' => array_intersect_key($given, $fields)];
        }

        return $clean;
    }
}
