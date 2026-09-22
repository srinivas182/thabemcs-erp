<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\UserInvitation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['max_users' => 3]);
    $this->admin = userWithRole($this->company);
});

it('shows only the people in the admin\'s own company', function (): void {
    User::factory()->forCompany($this->company)->create();
    User::factory()->forCompany(Company::factory()->create())->count(4)->create();

    $this->actingAs($this->admin)
        ->get('/settings/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/users/index')
            ->has('users', 2)
            ->where('quota.used', 2)
            ->where('quota.limit', 3));
});

it('invites a person with a role and emails them', function (): void {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post('/settings/users', [
            'name' => 'Sipho Dlamini',
            'email' => 'Sipho@Example.test',
            'job_title' => 'Site Manager',
            'phone' => '082 123 4567',
            'role' => Role::SiteManager->value,
        ])
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'sipho@example.test')->firstOrFail();
    expect($user->company_id)->toBe($this->company->id)
        ->and($user->phone)->toBe('0821234567');

    setPermissionsTeamId($this->company->id);
    expect($user->hasRole(Role::SiteManager->value))->toBeTrue();
    Notification::assertSentTo($user, UserInvitation::class);
});

it('stops inviting once the company user limit is reached', function (): void {
    Notification::fake();
    User::factory()->forCompany($this->company)->count(2)->create();

    $this->actingAs($this->admin)
        ->post('/settings/users', ['name' => 'One Too Many', 'email' => 'extra@example.test', 'role' => Role::Finance->value])
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'extra@example.test')->exists())->toBeFalse();
});

it('changes a role and deactivates a person', function (): void {
    $person = userWithRole($this->company, Role::ProjectManager);

    $this->actingAs($this->admin)->patch("/settings/users/{$person->ulid}", ['role' => Role::Director->value])->assertSessionHas('success');
    $this->actingAs($this->admin)->patch("/settings/users/{$person->ulid}", ['is_active' => false])->assertSessionHas('success');

    setPermissionsTeamId($this->company->id);
    $person->refresh()->unsetRelation('roles');
    expect($person->hasRole(Role::Director->value))->toBeTrue()
        ->and($person->hasRole(Role::ProjectManager->value))->toBeFalse()
        ->and($person->is_active)->toBeFalse();
});

it('does not let admins deactivate themselves', function (): void {
    $this->actingAs($this->admin)->patch("/settings/users/{$this->admin->ulid}", ['is_active' => false])->assertSessionHas('error');

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('hides people in other companies', function (): void {
    $outsider = User::factory()->forCompany(Company::factory()->create())->create();

    $this->actingAs($this->admin)->patch("/settings/users/{$outsider->ulid}", ['is_active' => false])->assertNotFound();
    expect($outsider->fresh()->is_active)->toBeTrue();
});

it('only lets Company Admins manage people', function (): void {
    $pm = userWithRole($this->company, Role::ProjectManager);

    $this->actingAs($pm)->get('/settings/users')->assertForbidden();
    $this->actingAs($pm)->post('/settings/users', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'finance'])->assertForbidden();
});
