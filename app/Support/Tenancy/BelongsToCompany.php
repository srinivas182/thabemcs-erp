<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domains\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a company (tenant).
 *
 * - Adds {@see CompanyScope} so every query is filtered to the current company.
 * - Stamps company_id automatically on create from the current company context.
 * - Prevents a record from being moved to another company after creation.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', app(CurrentCompany::class)->require()->getKey());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id') && $model->getOriginal('company_id') !== null) {
                throw new \LogicException('Records cannot be moved between companies.');
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
