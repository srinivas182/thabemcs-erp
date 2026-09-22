<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Models\User;
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

/**
 * Create an active user in the company with the given role.
 */
function userWithRole(Company $company, Role $role = Role::CompanyAdmin): User
{
    $user = User::factory()->forCompany($company)->create();

    $previous = getPermissionsTeamId();
    setPermissionsTeamId($company->getKey());
    $user->assignRole($role->value);
    setPermissionsTeamId($previous);

    return $user;
}
