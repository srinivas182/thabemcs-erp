<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A page on the public website, built from blocks. Editing saves a version first, so any change can be
 * rolled back, and a draft can be worked on without touching what the public sees.
 *
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string $slug
 * @property string $template
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property array<string, string>|null $seo
 * @property string $status draft|published|archived
 * @property Carbon|null $published_at
 * @property Carbon|null $publish_from
 * @property bool $is_home
 * @property bool $show_in_search
 * @property int $version
 * @property int $created_by
 * @property int|null $updated_by
 * @property-read Collection<int, CmsPageVersion> $versions
 */
class CmsPage extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['title', 'slug', 'template', 'blocks', 'seo', 'status', 'published_at', 'publish_from', 'is_home', 'show_in_search', 'version', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'seo' => 'array', 'published_at' => 'datetime', 'publish_from' => 'datetime', 'is_home' => 'boolean', 'show_in_search' => 'boolean', 'version' => 'integer'];
    }

    /**
     * @return HasMany<CmsPageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class)->orderByDesc('version');
    }

    public function isLive(): bool
    {
        return $this->status === 'published'
            && ($this->publish_from === null || $this->publish_from->isPast());
    }

    public function path(): string
    {
        return $this->is_home ? '/' : '/'.$this->slug;
    }
}
