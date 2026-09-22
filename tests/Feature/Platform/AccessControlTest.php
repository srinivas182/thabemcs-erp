<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Models\User;

it('sends guests to the sign-in page', function (): void {
    $this->get('/')->assertRedirect('/login');
});

it('blocks users of a suspended company', function (): void {
    $company = Company::factory()->suspended()->create();
    $user = User::factory()->forCompany($company)->create();

    $this->actingAs($user)->get('/')->assertForbidden();
});

it('blocks deactivated users', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->inactive()->create();

    $this->actingAs($user)->get('/')->assertForbidden();
});

it('lets a Super Admin act as a company and return to the platform view', function (): void {
    $company = Company::factory()->create(['name' => 'Gamma Homes']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('platform.acting-company.store', $company))
        ->assertRedirect(route('my-day'))
        ->assertSessionHas('acting_company_id', $company->id);

    $this->actingAs($admin)
        ->withSession(['acting_company_id' => $company->id])
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('company.name', 'Gamma Homes'));

    $this->actingAs($admin)
        ->withSession(['acting_company_id' => $company->id])
        ->delete(route('platform.acting-company.destroy'))
        ->assertRedirect(route('my-day'))
        ->assertSessionMissing('acting_company_id');

    $this->actingAs($admin)->get('/')->assertInertia(fn ($page) => $page->where('company', null));
});

it('does not let company users act as another company', function (): void {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    $this->actingAs($user)
        ->post(route('platform.acting-company.store', $other))
        ->assertForbidden();
});
