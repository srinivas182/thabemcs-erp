<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Jobs\RunCompanyMaintenance;
use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Jobs\SnapshotProjectProgress;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Models\ProgressSnapshot;
use App\Domains\Programme\Services\EarnedValueService;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->companyA = Company::factory()->create(['name' => 'Thabekhulu Developments']);
    $this->companyB = Company::factory()->create(['name' => 'Thabekhulu Coastal']);
});

it('reports readiness for the load balancer', function (): void {
    $this->getJson('/health')->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.cache.ok', true)
        ->assertJsonPath('checks.queue.ok', true)
        ->assertJsonPath('checks.storage.ok', true);
});

it('queues nightly work per company on its own queue instead of one long run', function (): void {
    Queue::fake();
    Company::factory()->create(['status' => 'suspended']);

    Artisan::call('suppliers:compliance-alerts');
    Artisan::call('approvals:escalate');
    Artisan::call('reports:send-scheduled');
    Artisan::call('popia:retention');

    // Two active companies, so two jobs per task; the suspended company is left out.
    Queue::assertPushed(RunCompanyMaintenance::class, 8);
    Queue::assertPushed(fn (RunCompanyMaintenance $job): bool => $job->task === 'compliance-alerts' && $job->queue === 'maintenance');
    Queue::assertPushed(fn (RunCompanyMaintenance $job): bool => $job->task === 'reports-send' && $job->queue === 'reports');
    Queue::assertNotPushed(fn (RunCompanyMaintenance $job): bool => $job->companyId === Company::query()->where('status', 'suspended')->value('id'));
});

it('queues an earned-value reading per project and records it', function (): void {
    $pm = userWithRole($this->companyA, Role::ProjectManager);
    $project = inCompany($this->companyA, function () use ($pm) {
        $p = Project::factory()->create(['project_manager_id' => $pm->id]);
        ProgrammeActivity::query()->create(['project_id' => $p->id, 'name' => 'Earthworks', 'planned_start' => '2027-04-05', 'duration_days' => 5, 'percent_complete' => 50]);

        return $p;
    });

    Queue::fake();
    Artisan::call('programme:snapshot');
    Queue::assertPushed(fn (SnapshotProjectProgress $job): bool => $job->projectId === $project->id && $job->queue === 'metrics');

    // The job itself records the reading when it runs.
    (new SnapshotProjectProgress((int) $this->companyA->getKey(), $project->id))->handle(app(EarnedValueService::class), app(CurrentCompany::class));
    expect(inCompany($this->companyA, fn () => ProgressSnapshot::query()->where('project_id', $project->id)->exists()))->toBeTrue();
});

it('sends a company its own maintenance work only', function (): void {
    inCompany($this->companyA, function (): void {
        $supplier = Supplier::query()->create(['name' => 'Expiring Supplies', 'type' => 'supplier', 'cidb_grade' => 9]);
        SupplierDocument::query()->create(['supplier_id' => $supplier->id, 'type' => 'tax_clearance', 'expires_on' => now()->addDays(7)->toDateString()]);
    });
    userWithRole($this->companyA, Role::Procurement);
    $otherBuyer = userWithRole($this->companyB, Role::Procurement);

    (new RunCompanyMaintenance((int) $this->companyA->getKey(), 'compliance-alerts'))
        ->handle(app(Container::class), app(CurrentCompany::class));

    expect($otherBuyer->notifications()->count())->toBe(0);
});

it('keeps cache, sessions and queues in separate places', function (): void {
    // With Redis, SESSION_CONNECTION puts sessions on their own Redis database. Left unset, sessions use
    // the default connection, which is what a deployment without Redis needs.
    config(['session.connection' => env('SESSION_CONNECTION')]);
    expect(config('session.connection'))->toBeNull()
        ->and(config('database.redis.queue.database'))->toBe('3')
        ->and(config('database.redis.sessions.database'))->toBe('2')
        ->and(config('queue.connections.redis.connection'))->toBe('queue')
        ->and(config('filesystems.disks.documents_s3.driver'))->toBe('s3');
});
