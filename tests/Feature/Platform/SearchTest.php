<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->alpha = Company::factory()->create(['name' => 'Alpha Developments']);
    $this->beta = Company::factory()->create(['name' => 'Beta Construction']);
    inCompany($this->alpha, fn () => Project::factory()->create(['name' => 'Ballito Heights', 'code' => 'BAL-01']));
    inCompany($this->beta, fn () => Project::factory()->create(['name' => 'Ballito Lagoon', 'code' => 'BAL-02']));
});

it('finds projects in the user\'s own company only', function (): void {
    $user = userWithRole($this->alpha, Role::ProjectManager);

    $results = $this->actingAs($user)->getJson('/search?q=ballito')->assertOk()->json('results');

    expect($results)->toHaveCount(1)
        ->and($results[0]['title'])->toBe('Ballito Heights');
});

it('includes people for Company Admins and companies for Super Admins', function (): void {
    $admin = userWithRole($this->alpha);
    User::factory()->forCompany($this->alpha)->create(['name' => 'Bongani Zulu']);

    $types = collect($this->actingAs($admin)->getJson('/search?q=bongani')->json('results'))->pluck('type');
    expect($types->all())->toBe(['person']);

    $super = User::factory()->superAdmin()->create();
    $types = collect($this->actingAs($super)->getJson('/search?q=beta')->json('results'))->pluck('type');
    expect($types->all())->toContain('company');
});

it('ignores searches shorter than two characters', function (): void {
    $user = userWithRole($this->alpha);

    expect($this->actingAs($user)->getJson('/search?q=b')->json('results'))->toBe([]);
});
