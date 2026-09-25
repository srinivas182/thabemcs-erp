<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A news or insight article.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $cms_category_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property array<string, string>|null $seo
 * @property int|null $hero_media_id
 * @property string|null $author_name
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $created_by
 * @property-read CmsCategory|null $category
 * @property-read CmsMedia|null $hero
 */
class CmsPost extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['cms_category_id', 'title', 'slug', 'excerpt', 'blocks', 'seo', 'hero_media_id', 'author_name', 'status', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'seo' => 'array', 'published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<CmsCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'cms_category_id');
    }

    /**
     * @return BelongsTo<CmsMedia, $this>
     */
    public function hero(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'hero_media_id');
    }
}
