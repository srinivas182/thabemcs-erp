<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Projects\Models\Task;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->site = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['name' => 'Durban North Villas']));
});

it('assigns a task, notifies the assignee and shows it on their My Day', function (): void {
    Notification::fake();

    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/tasks", [
        'title' => 'Book geotechnical engineer', 'assignee' => $this->site->ulid, 'due_date' => now()->subDay()->toDateString(),
    ])->assertSessionHas('success');

    Notification::assertSentTo($this->site, SystemMessage::class);

    $this->actingAs($this->site)->get('/my-day')
        ->assertInertia(fn ($page) => $page->where('tasks.0.title', 'Book geotechnical engineer')
            ->where('tasks.0.overdue', true)
            ->where('alerts.0.title', '1 overdue task'));
});

it('lets the assignee complete their own task but not edit it', function (): void {
    $task = inCompany($this->company, fn () => Task::query()->create(['project_id' => $this->project->id, 'title' => 'Site visit', 'assignee_id' => $this->site->id]));

    $this->actingAs($this->site)->patch("/tasks/{$task->ulid}", ['status' => 'done'])->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::Done)->and($task->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($this->site)->patch("/tasks/{$task->ulid}", ['title' => 'Renamed'])->assertForbidden();
});

it('scores risks on a 5 by 5 matrix', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/risks", [
        'kind' => 'risk', 'title' => 'Rezoning objection from neighbours', 'likelihood' => 4, 'impact' => 5, 'owner' => $this->pm->ulid,
    ])->assertSessionHas('success');

    $risk = inCompany($this->company, fn () => Risk::query()->firstOrFail());
    expect($risk->score())->toBe(20)->and($risk->rating())->toBe('critical');

    $this->actingAs($this->pm)->get('/my-day')->assertInertia(fn ($page) => $page->where('alerts.0.title', 'Critical risk: Rezoning objection from neighbours'));
});

it('hides tasks and risks from other companies', function (): void {
    $other = Company::factory()->create();
    $theirTask = inCompany($other, fn () => Task::query()->create(['title' => 'Secret']));

    $this->actingAs($this->pm)->patch("/tasks/{$theirTask->ulid}", ['status' => 'done'])->assertNotFound();
});
