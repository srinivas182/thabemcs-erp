<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
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

    // Roles that approve work or move money must have two-factor authentication; tests get it already
    // set up so they exercise the feature under test, not the sign-in requirement. Clear
    // two_factor_confirmed_at in a test that is checking the requirement itself.
    if (in_array($role->value, (array) config('platform.two_factor_required_roles', []), true)) {
        $user->forceFill(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();
    }

    return $user;
}

/**
 * A supplier holding every compliance document its type requires (valid for a year).
 */
function compliantSupplier(Company $company, string $name, string $type = 'supplier', ?string $vat = '4123456789'): Supplier
{
    return inCompany($company, function () use ($name, $type, $vat) {
        $supplier = Supplier::query()->create(['name' => $name, 'type' => $type, 'vat_number' => $vat, 'cidb_grade' => 9]);
        foreach (array_keys(config("supplier_compliance.required.{$type}")) as $doc) {
            SupplierDocument::query()->create([
                'supplier_id' => $supplier->id, 'type' => $doc, 'expires_on' => now()->addYear()->toDateString(),
            ]);
        }

        return $supplier;
    });
}
