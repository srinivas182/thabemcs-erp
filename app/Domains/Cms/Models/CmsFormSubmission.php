<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a visitor sent through a form on the website.
 *
 * @property int $id
 * @property string $ulid
 * @property int $cms_form_id
 * @property array<string, mixed> $answers
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $consented
 * @property string|null $ip_address
 * @property string|null $page
 * @property int|null $buyer_id
 * @property int|null $tenant_id
 * @property string $status
 * @property int|null $handled_by
 * @property Carbon $created_at
 */
class CmsFormSubmission extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['cms_form_id', 'answers', 'name', 'email', 'phone', 'consented', 'ip_address', 'page', 'buyer_id', 'tenant_id', 'status', 'handled_by'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'consented' => 'boolean'];
    }

    /**
     * @return BelongsTo<CmsForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(CmsForm::class, 'cms_form_id');
    }
}
