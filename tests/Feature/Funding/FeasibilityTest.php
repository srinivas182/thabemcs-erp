<?php

declare(strict_types=1);

use App\Domains\Feasibility\Enums\FeasibilityStatus;
use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Models\FeasibilityLine;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->dm = userWithRole($this->company, Role::DevelopmentManager);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());
});

function scenarioLines(): array
{
    return [
        ['category' => 'land', 'description' => 'Erf 1234 Ballito', 'basis' => 'amount', 'amount' => 8000000, 'rate' => null, 'start_month' => 1, 'end_month' => 1],
        ['category' => 'construction', 'description' => 'Construction', 'basis' => 'amount', 'amount' => 30000000, 'rate' => null, 'start_month' => 3, 'end_month' => 18],
        ['category' => 'professional_fees', 'description' => 'Professional team', 'basis' => 'percent_of_construction', 'amount' => null, 'rate' => 12, 'start_month' => 1, 'end_month' => 18],
        ['category' => 'revenue', 'description' => '48 units', 'basis' => 'amount', 'amount' => 52000000, 'rate' => null, 'start_month' => 16, 'end_month' => 24],
    ];
}

it('creates a scenario from the standard SA template', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/feasibility", ['name' => 'Base case', 'duration_months' => 24, 'units' => 48])
        ->assertRedirect();

    $scenario = inCompany($this->company, fn () => Feasibility::query()->with('lines')->firstOrFail());
    expect($scenario->lines->pluck('description'))->toContain('Transfer duty', 'Construction contract', 'Unit sales');
});

it('saves lines and returns the calculated appraisal', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/feasibility", ['name' => 'Base case', 'duration_months' => 24]);
    $scenario = inCompany($this->company, fn () => Feasibility::query()->firstOrFail());

    $this->actingAs($this->pm)->put("/feasibilities/{$scenario->ulid}", ['duration_months' => 24, 'units' => 48, 'lines' => scenarioLines()])
        ->assertSessionHas('success');

    $this->actingAs($this->pm)->get("/projects/{$this->project->ulid}/feasibility")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/feasibility')
            ->where('scenario.results.cost', 41600000)
            ->where('scenario.results.profit', 10400000)
            ->has('scenario.results.monthly', 24));
});

it('rejects lines scheduled outside the project duration', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/feasibility", ['name' => 'Base case', 'duration_months' => 12]);
    $scenario = inCompany($this->company, fn () => Feasibility::query()->firstOrFail());

    $lines = scenarioLines();
    $lines[3]['end_month'] = 30;

    $this->actingAs($this->pm)->put("/feasibilities/{$scenario->ulid}", ['duration_months' => 12, 'lines' => $lines])
        ->assertSessionHasErrors('lines.3.end_month');
});

it('approves one baseline per project and locks it', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/feasibility", ['name' => 'Base case', 'duration_months' => 24]);
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/feasibility", ['name' => 'Slower sales', 'duration_months' => 30]);
    [$base, $slow] = inCompany($this->company, fn () => Feasibility::query()->orderBy('id')->get()->all());

    $this->actingAs($this->pm)->post("/feasibilities/{$base->ulid}/approve")->assertForbidden();

    $this->actingAs($this->dm)->post("/feasibilities/{$base->ulid}/approve")->assertSessionHas('success');
    $this->actingAs($this->dm)->post("/feasibilities/{$slow->ulid}/approve")->assertSessionHas('success');

    expect($base->fresh()->is_baseline)->toBeFalse()
        ->and($slow->fresh()->is_baseline)->toBeTrue()
        ->and($slow->fresh()->status)->toBe(FeasibilityStatus::Approved);

    $this->actingAs($this->pm)->put("/feasibilities/{$slow->ulid}", ['duration_months' => 30, 'lines' => []])->assertSessionHas('error');
    expect(inCompany($this->company, fn () => FeasibilityLine::query()->where('feasibility_id', $slow->id)->count()))->toBeGreaterThan(0);
});
