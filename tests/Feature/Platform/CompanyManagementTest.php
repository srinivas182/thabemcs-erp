<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\CompanyStatus;
use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\UserInvitation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->superAdmin()->create();
});

function validCompany(array $overrides = []): array
{
    return [
        'name' => 'Umhlanga Ridge Developments',
        'legal_name' => 'Umhlanga Ridge Developments (Pty) Ltd',
        'registration_number' => '2021/123456/07',
        'vat_number' => '4123456789',
        'max_projects' => 50,
        'max_users' => 20,
        'max_storage_mb' => null,
        'modules' => [Module::Projects->value, Module::Site->value],
        'admin_name' => 'Thandi Mokoena',
        'admin_email' => 'thandi@umhlanga.test',
        ...$overrides,
    ];
}

it('lists companies with their usage for the Super Admin', function (): void {
    Company::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get('/platform/companies')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('platform/companies/index')->has('companies', 2));
});

it('creates a company with limits and modules and invites its administrator', function (): void {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post('/platform/companies', validCompany())
        ->assertRedirect('/platform/companies')
        ->assertSessionHas('success');

    $company = Company::query()->where('name', 'Umhlanga Ridge Developments')->firstOrFail();
    expect($company->max_projects)->toBe(50)
        ->and($company->modules)->toBe(['projects', 'site'])
        ->and($company->status)->toBe(CompanyStatus::Active);

    $invited = User::query()->where('email', 'thandi@umhlanga.test')->firstOrFail();
    expect($invited->company_id)->toBe($company->id);

    setPermissionsTeamId($company->id);
    expect($invited->hasRole(Role::CompanyAdmin->value))->toBeTrue();

    Notification::assertSentTo($invited, UserInvitation::class);
});

it('validates South African registration and VAT numbers', function (): void {
    $this->actingAs($this->admin)
        ->post('/platform/companies', validCompany(['registration_number' => '12345', 'vat_number' => '999']))
        ->assertSessionHasErrors(['registration_number', 'vat_number']);
});

it('updates limits and modules', function (): void {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)
        ->put("/platform/companies/{$company->ulid}", [...validCompany(['name' => $company->name, 'max_projects' => 5]), 'modules' => ['finance']])
        ->assertRedirect('/platform/companies');

    expect($company->fresh()->max_projects)->toBe(5)
        ->and($company->fresh()->modules)->toBe(['finance']);
});

it('suspends a company, which locks its users out', function (): void {
    $company = Company::factory()->create();
    $member = User::factory()->forCompany($company)->create();

    $this->actingAs($this->admin)
        ->patch("/platform/companies/{$company->ulid}/status", ['status' => 'suspended'])
        ->assertSessionHas('success');

    expect($company->fresh()->status)->toBe(CompanyStatus::Suspended);
    $this->actingAs($member)->get('/')->assertForbidden();
});

it('keeps platform administration away from company users', function (): void {
    $company = Company::factory()->create();
    $companyAdmin = userWithRole($company);

    $this->actingAs($companyAdmin)->get('/platform/companies')->assertForbidden();
    $this->actingAs($companyAdmin)->post('/platform/companies', validCompany())->assertForbidden();
});
