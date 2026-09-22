<?php

declare(strict_types=1);

namespace App\Domains\Forms\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A custom checklist built with the form builder and filled in on the site app.
 * Field types: text, number, yesno, passfail, choice, photo, date. Editing bumps the version.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $kind quality|safety|checklist
 * @property list<array{id: string, label: string, type: string, required: bool, options?: list<string>}> $fields
 * @property bool $active
 * @property int $version
 * @property int $created_by
 */
class FormTemplate extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['name', 'kind', 'fields', 'active', 'version', 'created_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'active' => 'boolean', 'version' => 'integer'];
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
