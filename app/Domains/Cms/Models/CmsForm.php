<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A form on the website. Its fields are defined here, and what a submission becomes.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property list<array{name: string, label: string, type: string, required: bool, options?: list<string>}> $fields
 * @property list<string>|null $recipients
 * @property string $creates none|buyer|tenant
 * @property string|null $success_message
 * @property bool $active
 * @property int $created_by
 * @property-read Collection<int, CmsFormSubmission> $submissions
 */
class CmsForm extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['name', 'slug', 'fields', 'recipients', 'creates', 'success_message', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'recipients' => 'array', 'active' => 'boolean'];
    }

    /**
     * @return HasMany<CmsFormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(CmsFormSubmission::class, 'cms_form_id');
    }
}
