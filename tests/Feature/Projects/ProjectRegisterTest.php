<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['max_projects' => 10]);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
});

function projectPayload(array $overrides = []): array
{
    return [
        'name' => 'Ballito Heights',
        'development_type' => 'townhouses',
        'province' => 'KZN',
        'town' => 'Ballito',
        'estimated_value' => 48500000,
        'planned_start_date' => '2027-02-01',
        'planned_completion_date' => '2028-06-30',
        ...$overrides,
    ];
}

it('creates a project with the next code and the full stage-gate checklist', function (): void {
    $this->actingAs($this->pm)->post('/projects', projectPayload(['project_manager' => $this->pm->ulid]))->assertRedirect();

    $project = inCompany($this->company, fn () => Project::query()->firstOrFail());
    expect($project->code)->toBe('PRJ-0001')
        ->and($project->stage)->toBe(ProjectStage::Plan)
        ->and($project->project_manager_id)->toBe($this->pm->id)
        ->and(inCompany($this->company, fn () => StageGateItem::query()->where('project_id', $project->id)->count()))
        ->toBe(collect(config('stage_gates'))->flatten(1)->count());
});

it('validates dates, province and that the location is in South Africa', function (): void {
    $this->actingAs($this->pm)->post('/projects', projectPayload([
        'planned_completion_date' => '2026-01-01', 'province' => 'XX', 'latitude' => 51.5,
    ]))->assertSessionHasErrors(['planned_completion_date', 'province', 'latitude']);
});

it('rejects a project manager from another company', function (): void {
    $outsider = userWithRole(Company::factory()->create(), Role::ProjectManager);

    $this->actingAs($this->pm)->post('/projects', projectPayload(['project_manager' => $outsider->ulid]))
        ->assertSessionHasErrors('project_manager');
});

it('lists and filters projects by stage', function (): void {
    inCompany($this->company, function (): void {
        Project::factory()->count(2)->create();
        Project::factory()->inStage(ProjectStage::Build)->create();
    });

    $this->actingAs($this->pm)->get('/projects?stage=build')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/index')->has('projects.data', 1)->where('stages.4.count', 1));
});

it('shows a project page but hides other companies\' projects', function (): void {
    $mine = inCompany($this->company, fn () => Project::factory()->create());
    $theirs = inCompany(Company::factory()->create(), fn () => Project::factory()->create());

    $this->actingAs($this->pm)->get("/projects/{$mine->ulid}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/show')->where('project.code', $mine->code));
    $this->actingAs($this->pm)->get("/projects/{$theirs->ulid}")->assertNotFound();
});

it('only lets project roles create projects', function (): void {
    $finance = userWithRole($this->company, Role::Finance);

    $this->actingAs($finance)->get('/projects')->assertOk();
    $this->actingAs($finance)->post('/projects', projectPayload())->assertForbidden();
});

it('hides the projects module when it is switched off', function (): void {
    $this->company->update(['modules' => [Module::Finance->value]]);

    $this->actingAs($this->pm)->get('/projects')->assertForbidden();
});
