<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\StageGateItem;
use App\Domains\Projects\Models\StageTransition;
use App\Domains\Projects\Services\ProjectService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->director = userWithRole($this->company, Role::Director);
    $this->project = inCompany($this->company, fn () => app(ProjectService::class)
        ->create(['name' => 'Umhlanga Ridge', 'development_type' => 'apartments', 'province' => 'KZN', 'project_manager_id' => $this->pm->id], $this->pm));
});

function planItems(Project $project): Collection
{
    return StageGateItem::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('stage', 'plan')->get();
}

it('will not advance while required checklist items are open', function (): void {
    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/advance")->assertSessionHas('error');

    expect($this->project->fresh()->stage)->toBe(ProjectStage::Plan);
});

it('lets the project manager tick items and a director approve the gate', function (): void {
    Notification::fake();

    foreach (planItems($this->project) as $item) {
        $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/gate-items/{$item->id}", ['complete' => true])->assertRedirect();
    }

    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/advance", ['comment' => 'Brief signed off at board'])
        ->assertSessionHas('success');

    expect($this->project->fresh()->stage)->toBe(ProjectStage::Fund);

    $transition = StageTransition::query()->withoutGlobalScopes()->where('project_id', $this->project->id)->firstOrFail();
    expect($transition->from_stage)->toBe(ProjectStage::Plan)
        ->and($transition->approved_by)->toBe($this->director->id)
        ->and($transition->comment)->toBe('Brief signed off at board');

    Notification::assertSentTo($this->pm, SystemMessage::class);
});

it('does not let project managers approve gates', function (): void {
    planItems($this->project)->each(fn ($i) => $i->forceFill(['completed_at' => now()])->save());

    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/advance")->assertForbidden();
});

it('locks checklists of stages other than the current one', function (): void {
    $fundItem = StageGateItem::query()->withoutGlobalScopes()->where('project_id', $this->project->id)->where('stage', 'fund')->firstOrFail();

    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/gate-items/{$fundItem->id}", ['complete' => true])
        ->assertSessionHas('error');

    expect($fundItem->fresh()->completed_at)->toBeNull();
});

it('lists a completed gate under the approver\'s approvals on My Day', function (): void {
    planItems($this->project)->each(fn ($i) => $i->forceFill(['completed_at' => now()])->save());

    $this->actingAs($this->director)->get('/')
        ->assertInertia(fn ($page) => $page->has('approvals', 1)->where('approvals.0.title', 'Umhlanga Ridge: Plan gate'));
});
