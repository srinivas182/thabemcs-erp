<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domains\Platform\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Lets scheduled work run for one company (as a queued job, in parallel) or for every active company
 * (in one process, for local use and tests).
 */
trait IteratesCompanies
{
    /**
     * @return Collection<int, Company>|LazyCollection<int, Company>
     */
    private function companies(?Company $only): Collection|LazyCollection
    {
        return $only !== null ? collect([$only]) : Company::query()->where('status', 'active')->cursor();
    }
}
