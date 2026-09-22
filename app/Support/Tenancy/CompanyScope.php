<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries on company-owned models to the current company.
 *
 * Fails closed: with no company context and no platform access, the query
 * returns no rows rather than leaking another company's data.
 *
 * @implements Scope<Model>
 */
final class CompanyScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(CurrentCompany::class);

        if ($context->check()) {
            $builder->where($model->qualifyColumn('company_id'), $context->id());

            return;
        }

        if ($context->hasPlatformAccess()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
