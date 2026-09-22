<?php

declare(strict_types=1);

namespace App\Domains\Documents\Models;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Platform\Enums\Role;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A document with its version history. Files are never public; they are streamed after a permission check.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $project_id
 * @property string $folder
 * @property string $title
 * @property DocumentCategory $category
 * @property string|null $drawing_number
 * @property string|null $drawing_discipline
 * @property list<string>|null $restricted_to_roles
 * @property int|null $created_by
 * @property-read Project|null $project
 * @property-read DocumentVersion|null $latestVersion
 * @property-read Collection<int, DocumentVersion> $versions
 */
class Document extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = ['project_id', 'folder', 'title', 'category', 'drawing_number', 'drawing_discipline', 'restricted_to_roles', 'created_by'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class, 'restricted_to_roles' => 'array'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'folder', 'category', 'restricted_to_roles'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasOne<DocumentVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version');
    }

    /**
     * Restricted documents are visible only to the listed roles, plus Company Admins, Directors and Super Admins.
     */
    public function isVisibleTo(User $user): bool
    {
        $roles = $this->restricted_to_roles;

        if ($roles === null || $roles === [] || $user->is_super_admin) {
            return true;
        }

        return $user->hasAnyRole([...$roles, Role::CompanyAdmin->value, Role::Director->value]);
    }
}
