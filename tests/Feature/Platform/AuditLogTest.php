<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->alpha = Company::factory()->create();
    $this->beta = Company::factory()->create();
});

it('stamps every audit entry with the company it belongs to', function (): void {
    $project = inCompany($this->alpha, fn () => Project::factory()->create());

    $entry = Activity::query()->where('subject_type', $project->getMorphClass())->where('subject_id', $project->id)->firstOrFail();
    expect($entry->getAttribute('company_id'))->toBe($this->alpha->id);
});

it('shows Company Admins only their own company\'s audit trail', function (): void {
    inCompany($this->alpha, fn () => Project::factory()->count(2)->create());
    inCompany($this->beta, fn () => Project::factory()->count(3)->create());

    $admin = userWithRole($this->alpha);
    $own = Activity::query()->where('company_id', $this->alpha->id)->count();

    expect($own)->toBeGreaterThanOrEqual(2)
        ->and(Activity::query()->where('company_id', $this->beta->id)->count())->toBeGreaterThanOrEqual(3);

    $this->actingAs($admin)->get('/settings/activity')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/activity')->has('activities.data', $own));
});

it('lets Directors read the audit log but not project managers', function (): void {
    $this->actingAs(userWithRole($this->alpha, Role::Director))->get('/settings/activity')->assertOk();
    $this->actingAs(userWithRole($this->alpha, Role::ProjectManager))->get('/settings/activity')->assertForbidden();
});
