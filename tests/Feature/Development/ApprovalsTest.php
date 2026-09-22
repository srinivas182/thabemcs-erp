<?php

declare(strict_types=1);

use App\Domains\Approvals\Enums\ApplicationStatus;
use App\Domains\Approvals\Models\StatutoryApplication;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['name' => 'Umhlanga Ridge']));
});

it('adds an application and moves it through submission to approval', function (): void {
    $this->actingAs($this->pm)->post('/approvals', [
        'project' => $this->project->ulid, 'type' => 'building_plans', 'authority' => 'eThekwini Municipality', 'reference_number' => 'BP/2026/1187',
    ])->assertSessionHas('success');

    $application = inCompany($this->company, fn () => StatutoryApplication::query()->firstOrFail());
    $this->actingAs($this->pm)->patch("/approvals/{$application->ulid}", ['status' => 'approved', 'decision_on' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString()])
        ->assertSessionHas('success');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
});

it('warns on My Day when an approval is about to lapse or a decision is overdue', function (): void {
    inCompany($this->company, function (): void {
        StatutoryApplication::query()->create(['project_id' => $this->project->id, 'type' => 'town_planning', 'status' => 'approved', 'decision_on' => now()->subYears(2), 'valid_until' => now()->addDays(10)]);
        StatutoryApplication::query()->create(['project_id' => $this->project->id, 'type' => 'environmental', 'status' => 'submitted', 'expected_decision_on' => now()->subDays(5)]);
    });

    $this->actingAs($this->pm)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('alerts.0.title', 'Town planning (rezoning, subdivision, consent) lapses in 10 days')
        ->where('alerts.0.level', 'danger')
        ->where('alerts.1.title', '1 application decision is overdue'));

    $this->actingAs($this->pm)->get('/approvals?view=attention')->assertInertia(fn (Assert $page) => $page->has('applications', 2));
});

it('rejects an application for another company\'s project', function (): void {
    $theirs = inCompany(Company::factory()->create(), fn () => Project::factory()->create());

    $this->actingAs($this->pm)->post('/approvals', ['project' => $theirs->ulid, 'type' => 'fire'])->assertSessionHasErrors('project');
});
