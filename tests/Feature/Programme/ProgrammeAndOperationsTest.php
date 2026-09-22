<?php

declare(strict_types=1);

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\MasterData\Models\CostCode;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Enums\TaskStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Services\TaskEscalation;
use App\Domains\Site\Models\SiteInstruction;
use App\Domains\Site\Models\SitePhoto;
use App\Domains\Site\Models\Snag;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeAllocation;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->safety = userWithRole($this->company, Role::SafetyOfficer);
    $this->qs = userWithRole($this->company, Role::QuantitySurveyor);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['project_manager_id' => $this->pm->id]));
});

function activity($test, string $name, string $start, int $days): ProgrammeActivity
{
    return inCompany($test->company, fn () => ProgrammeActivity::query()->create(['project_id' => $test->project->id, 'name' => $name, 'planned_start' => $start, 'duration_days' => $days]));
}

it('schedules on working days around public holidays and finds the critical path', function (): void {
    // Human Rights Day falls on Sunday 21 March 2027, so Monday 22 is a holiday; Good Friday 26 and Family Day 29 March.
    $a = activity($this, 'Foundations', '2027-03-15', 5);
    $b = activity($this, 'Ground floor slab', '2027-03-15', 3);
    $c = activity($this, 'Slab signed off', '2027-03-15', 0);
    $d = activity($this, 'Site hoarding', '2027-03-15', 2);

    $this->actingAs($this->pm)->post("/programme-activities/{$b->ulid}/links", ['predecessor' => $a->ulid])->assertSessionHas('success');
    $this->actingAs($this->pm)->post("/programme-activities/{$c->ulid}/links", ['predecessor' => $b->ulid])->assertSessionHas('success');

    $plan = inCompany($this->company, fn () => app(ScheduleService::class)->calculate($this->project));

    expect($plan['activities'][$a->id]['earlyFinish'])->toBe('2027-03-19')
        ->and($plan['activities'][$b->id]['earlyStart'])->toBe('2027-03-23')
        ->and($plan['activities'][$b->id]['earlyFinish'])->toBe('2027-03-25')
        ->and($plan['activities'][$c->id]['earlyStart'])->toBe('2027-03-30')
        ->and($plan['finish'])->toBe('2027-03-30')
        ->and($plan['activities'][$a->id]['critical'])->toBeTrue()
        ->and($plan['activities'][$d->id]['critical'])->toBeFalse()
        ->and($plan['activities'][$d->id]['float'])->toBe(6);

    // A link back from the milestone to the first activity would make a loop.
    $this->actingAs($this->pm)->post("/programme-activities/{$a->ulid}/links", ['predecessor' => $c->ulid])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'loop'));

    // Recording progress stamps the actual start.
    $this->actingAs($this->pm)->patch("/programme-activities/{$a->ulid}", ['percent_complete' => 40])->assertSessionHas('success');
    expect($a->fresh()->actual_start)->not->toBeNull();

    $this->actingAs($this->pm)->get("/projects/{$this->project->ulid}/programme")
        ->assertInertia(fn (Assert $page) => $page->component('projects/programme')->has('activities', 4)->where('finish', '2027-03-30'));
});

it('keeps minutes with action items that become tasks and notifies owners when issued', function (): void {
    Notification::fake();
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/meetings", ['type' => 'site', 'title' => 'Weekly site meeting', 'held_at' => now()->toDateTimeString()]);
    $meeting = inCompany($this->company, fn () => Meeting::query()->firstOrFail());
    expect($meeting->number)->toBe(1);

    $base = "/projects/{$this->project->ulid}/meetings/{$meeting->ulid}";
    $this->actingAs($this->pm)->put($base, ['minutes' => '1. Safety: scaffold tags missing on Block B.'])->assertSessionHas('success');
    $this->actingAs($this->pm)->post("{$base}/actions", ['title' => 'Tag all scaffolds on Block B', 'owner' => $this->safety->ulid, 'due_date' => now()->addDays(2)->toDateString()]);

    $task = inCompany($this->company, fn () => Task::query()->firstOrFail());
    expect($task->meeting_id)->toBe($meeting->id)->and($task->assignee_id)->toBe($this->safety->id);

    $this->actingAs($this->pm)->post("{$base}/issue")->assertSessionHas('success');
    Notification::assertSentTo($this->safety, SystemMessage::class, fn (SystemMessage $m) => str_contains($m->title, 'Tag all scaffolds'));
    $this->actingAs($this->pm)->put($base, ['minutes' => 'changed'])->assertSessionHas('error');
});

it('gives each company a cost code library that budgets pick from', function (): void {
    $finance = userWithRole($this->company, Role::Finance);
    $this->actingAs($finance)->get('/settings/master-data')->assertInertia(fn (Assert $page) => $page->component('settings/master-data')->where('costCodes.0.code', '01.01'));

    $code = inCompany($this->company, fn () => CostCode::query()->where('code', '05.03')->firstOrFail());
    $this->actingAs($this->qs)->post("/projects/{$this->project->ulid}/budget/lines", ['cost_code_id' => $code->id, 'original_amount' => 850_000])->assertSessionHas('success');
    expect(inCompany($this->company, fn () => BudgetLine::query()->firstOrFail()->description))->toBe('Concrete, formwork and reinforcement');

    $this->actingAs($this->qs)->post("/projects/{$this->project->ulid}/budget/lines", ['cost_code_id' => $code->id, 'original_amount' => 1])->assertSessionHasErrors('code');
});

it('shows missing legal appointments and flags expired competency', function (): void {
    $this->actingAs($this->safety)->get("/projects/{$this->project->ulid}/safety")
        ->assertInertia(fn (Assert $page) => $page->where('compliance.gaps', fn ($g) => collect($g)->contains('Construction manager')));

    $this->actingAs($this->safety)->post("/projects/{$this->project->ulid}/safety-appointments", [
        'type' => 'construction_manager', 'appointee_name' => 'Sipho Dlamini', 'appointed_on' => now()->subYear()->toDateString(), 'competency_expires_on' => now()->subDay()->toDateString(),
    ])->assertSessionHas('success');
    $this->actingAs($this->safety)->post("/projects/{$this->project->ulid}/safety-file", ['item' => 'hs_plan', 'status' => 'in_place']);

    $this->actingAs($this->safety)->get("/projects/{$this->project->ulid}/safety")
        ->assertInertia(fn (Assert $page) => $page->where('compliance.gaps', fn ($g) => ! collect($g)->contains('Construction manager'))
            ->where('compliance.appointments.0.expired', true)->where('compliance.fileComplete', 1));
});

it('takes the crew register, goods received with a photo, snags and site instructions from the site app', function (): void {
    $employee = inCompany($this->company, function () {
        $e = Employee::query()->create(['employee_number' => 'E7', 'first_name' => 'Themba', 'last_name' => 'Khumalo', 'employment_type' => 'temporary', 'start_date' => now()->subMonth(), 'days_per_week' => 5]);
        EmployeeAllocation::query()->create(['employee_id' => $e->id, 'project_id' => $this->project->id, 'from_date' => now()->subWeek()]);

        return $e;
    });
    $this->actingAs($this->siteManager)->getJson("/api/v1/site/employees?projectId={$this->project->ulid}")->assertJsonPath('data.0.name', 'Themba Khumalo');
    $this->actingAs($this->siteManager)->postJson('/api/v1/site/crew-attendance', [
        'projectId' => $this->project->ulid, 'date' => now()->toDateString(),
        'entries' => [['clientId' => (string) Str::uuid(), 'employeeId' => $employee->ulid, 'status' => 'present', 'timeIn' => '07:00', 'timeOut' => '16:30']],
    ])->assertCreated()->assertJsonPath('data.saved', 1);
    expect(inCompany($this->company, fn () => CrewAttendance::query()->count()))->toBe(1);

    $supplier = compliantSupplier($this->company, 'Build It Ballito');
    $order = inCompany($this->company, function () use ($supplier) {
        $o = PurchaseOrder::query()->create(['project_id' => $this->project->id, 'supplier_id' => $supplier->id, 'number' => 1, 'status' => 'issued', 'vat_applies' => true, 'created_by' => $this->pm->id]);
        PurchaseOrderLine::query()->create(['purchase_order_id' => $o->id, 'description' => 'Cement', 'quantity' => 400, 'unit' => 'bag', 'unit_price' => 120]);

        return $o;
    });
    $line = $order->lines()->firstOrFail();
    $this->actingAs($this->siteManager)->getJson("/api/v1/site/orders?projectId={$this->project->ulid}")->assertJsonPath('data.0.lines.0.outstanding', 400.0);

    $clientId = (string) Str::uuid();
    $payload = ['projectId' => $this->project->ulid, 'clientId' => $clientId, 'orderId' => $order->ulid, 'receivedOn' => now()->toDateString(), 'quantities' => json_encode([$line->id => 150]), 'photo' => UploadedFile::fake()->image('dn.jpg')];
    $this->actingAs($this->siteManager)->post('/api/v1/site/receipts', $payload, ['Accept' => 'application/json'])->assertCreated();
    $this->actingAs($this->siteManager)->post('/api/v1/site/receipts', [...$payload, 'photo' => UploadedFile::fake()->image('dn.jpg')], ['Accept' => 'application/json'])->assertOk();

    expect(inCompany($this->company, fn () => GoodsReceipt::query()->count()))->toBe(1)
        ->and($order->fresh()->status)->toBe('partially_received')
        ->and(inCompany($this->company, fn () => SitePhoto::query()->where('attachable_type', (new GoodsReceipt)->getMorphClass())->count()))->toBe(1);

    $this->actingAs($this->siteManager)->post('/api/v1/site/snags', ['projectId' => $this->project->ulid, 'clientId' => (string) Str::uuid(), 'description' => 'Cracked tile at shower', 'photo' => UploadedFile::fake()->image('snag.jpg')], ['Accept' => 'application/json'])->assertCreated();
    expect(inCompany($this->company, fn () => Snag::query()->firstOrFail()->description))->toBe('Cracked tile at shower');

    $this->actingAs($this->siteManager)->postJson('/api/v1/site/instructions', ['projectId' => $this->project->ulid, 'clientId' => (string) Str::uuid(), 'subject' => 'Move window W3', 'instruction' => 'Shift 300 mm left'])->assertCreated()->assertJsonPath('data.number', 1);
    $this->actingAs($this->safety)->postJson('/api/v1/site/instructions', ['projectId' => $this->project->ulid, 'clientId' => (string) Str::uuid(), 'subject' => 'x', 'instruction' => 'y'])->assertForbidden();
    expect(inCompany($this->company, fn () => SiteInstruction::query()->count()))->toBe(1);
});

it('escalates tasks more than two working days overdue to the project manager once', function (): void {
    Notification::fake();
    inCompany($this->company, fn () => Task::query()->create(['project_id' => $this->project->id, 'title' => 'Submit rebar schedule', 'assignee_id' => $this->siteManager->id, 'due_date' => now()->subDays(10), 'status' => TaskStatus::Open, 'priority' => 'normal', 'created_by' => $this->pm->id]));

    expect(app(TaskEscalation::class)->run())->toBe(1)->and(app(TaskEscalation::class)->run())->toBe(0);
    Notification::assertSentTo($this->pm, SystemMessage::class, fn (SystemMessage $m) => str_contains($m->title, 'Submit rebar schedule'));
});
