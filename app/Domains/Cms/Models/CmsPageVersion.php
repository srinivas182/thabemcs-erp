<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved copy of a page, so any change can be rolled back.
 *
 * @property int $id
 * @property int $cms_page_id
 * @property int $version
 * @property string $title
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property array<string, string>|null $seo
 * @property string|null $note
 * @property int $saved_by
 * @property Carbon $created_at
 */
class CmsPageVersion extends Model
{
    use BelongsToCompany;

    protected $fillable = ['cms_page_id', 'version', 'title', 'blocks', 'seo', 'note', 'saved_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'seo' => 'array', 'version' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
