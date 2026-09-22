<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Run a callback inside a company context (as queued jobs and seeders do).
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function inCompany(Company $company, callable $callback): mixed
{
    return app(CurrentCompany::class)->runFor($company, $callback);
}
