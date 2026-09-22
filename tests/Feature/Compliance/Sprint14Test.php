<?php

declare(strict_types=1);

use App\Domains\Compliance\Models\DataSubjectRequest;
use App\Domains\Compliance\Services\RetentionService;
use App\Domains\Contracts\Models\Contract;
use App\Domains\Documents\Models\Document;
use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Finance\Services\ExportService;
use App\Domains\Finance\Services\ProfitabilityService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Mail\RfqInvitationMail;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Procurement\Models\RfqInvitation;
use App\Domains\Procurement\Services\ProcurementService;
use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Services\EarnedValueService;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Models\Project;
use App\Domains\Site\Models\SiteAttendance;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\LeaveRequest;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['name' => 'Thabekhulu Developments']);
    $this->buyer = userWithRole($this->company, Role::Procurement);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->director = userWithRole($this->company, Role::Director);
    $this->admin = userWithRole($this->company, Role::CompanyAdmin);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['project_manager_id' => $this->pm->id]));
});

function approvedRequisition($test): Requisition
{
    return inCompany($test->company, function () use ($test) {
        $r = app(ProcurementService::class)->createRequisition($test->project, 'Roof sheeting', null, null, [
            ['description' => 'IBR sheeting 0.5 mm', 'quantity' => 120, 'unit' => 'sheet', 'estimated_unit_price' => 480],
        ], $test->pm);
        $r->update(['status' => 'approved']);

        return $r;
    });
}

it('emails RFQs and lets suppliers submit and revise a quote through their private link', function (): void {
    Mail::fake();
    Notification::fake();
    $requisition = approvedRequisition($this);
    $withEmail = compliantSupplier($this->company, 'Ballito Roofing');
    inCompany($this->company, fn () => $withEmail->update(['email' => 'quotes@ballitoroofing.co.za']));
    $noEmail = compliantSupplier($this->company, 'No Email Roofing');

    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/rfq", [
        'suppliers' => [$withEmail->ulid, $noEmail->ulid], 'closes_on' => now()->addDays(7)->toDateString(),
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, 'Ballito Roofing') && str_contains($m, 'No Email Roofing (no email address)'));

    $url = null;
    Mail::assertSent(RfqInvitationMail::class, function (RfqInvitationMail $m) use (&$url) {
        $url = $m->url;

        return $m->hasTo('quotes@ballitoroofing.co.za');
    });
    $token = basename((string) $url);
    expect(inCompany($this->company, fn () => RfqInvitation::query()->firstOrFail()->token_hash))->toBe(hash('sha256', $token));

    // The supplier sees the items but never the company's estimates.
    auth()->logout();
    $this->get("/quote/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('rfq/respond')
        ->where('lines.0.description', 'IBR sheeting 0.5 mm')->missing('lines.0.estimated_unit_price')->where('open', true));

    $this->post("/quote/{$token}", ['amount' => 55_000, 'lead_time_days' => 5, 'file' => UploadedFile::fake()->create('quote.pdf', 80, 'application/pdf')])->assertSessionHas('success');
    $this->post("/quote/{$token}", ['amount' => 53_500])->assertSessionHas('success');

    $quote = inCompany($this->company, fn () => RequisitionQuote::query()->sole());
    expect((float) $quote->amount)->toBe(53_500.0)->and($quote->submitted_by_supplier)->toBeTrue()->and($quote->document_id)->not->toBeNull();
    Notification::assertSentTo($this->buyer, SystemMessage::class, fn (SystemMessage $m) => str_contains($m->title, 'Quote received'));

    $this->get('/quote/'.Str::random(48))->assertNotFound();

    $this->travel(8)->days();
    $this->post("/quote/{$token}", ['amount' => 1_000])->assertSessionHas('error', 'Quotes for this request have closed.');
});

it('suggests contract terms from the chosen form', function (): void {
    $contractor = compliantSupplier($this->company, 'Zulu Construction', 'contractor');
    $qs = userWithRole($this->company, Role::QuantitySurveyor);

    $this->actingAs($qs)->get("/projects/{$this->project->ulid}/contracts")
        ->assertInertia(fn (Assert $page) => $page->where('formDefaults.nec4_ecc.payment_terms_days', 21));

    $this->actingAs($qs)->post("/projects/{$this->project->ulid}/contracts", [
        'supplier' => $contractor->ulid, 'reference' => 'BH-01', 'contract_form' => 'jbcc_pba', 'contract_sum' => 5_000_000,
        'retention_percent' => 10, 'retention_cap_percent' => 5, 'release_at_practical_percent' => 50, 'payment_terms_days' => 7, 'defects_period_months' => 3,
    ])->assertSessionHas('success');
    expect(inCompany($this->company, fn () => Contract::query()->firstOrFail()->payment_terms_days))->toBe(7);
});

it('skips the December building shutdown on the programme', function (): void {
    inCompany($this->company, fn () => ProgrammeActivity::query()->create(['project_id' => $this->project->id, 'name' => 'Roof trusses', 'planned_start' => '2027-12-13', 'duration_days' => 5]));
    $plan = inCompany($this->company, fn () => app(ScheduleService::class)->calculate($this->project));

    // 13, 14, 15 December, then the shutdown (16 Dec to 9 Jan), then 10 and 11 January.
    expect($plan['finish'])->toBe('2028-01-11');
});

it('measures earned value against the programme and the budget', function (): void {
    inCompany($this->company, function (): void {
        BudgetLine::query()->create(['project_id' => $this->project->id, 'code' => '05.01', 'description' => 'Building works', 'original_amount' => 1_000_000]);
        $a = ProgrammeActivity::query()->create(['project_id' => $this->project->id, 'name' => 'Substructure', 'planned_start' => '2027-03-01', 'duration_days' => 10, 'percent_complete' => 80]);
        $b = ProgrammeActivity::query()->create(['project_id' => $this->project->id, 'name' => 'Superstructure', 'planned_start' => '2027-03-01', 'duration_days' => 10]);
        ActivityDependency::query()->create(['predecessor_id' => $a->id, 'successor_id' => $b->id]);
        $supplier = compliantSupplier($this->company, 'Zulu Construction', 'contractor');
        $i = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'Z-1', 'invoice_date' => '2027-03-10', 'due_date' => '2027-04-10', 'subtotal' => 450_000, 'vat' => 67_500, 'total' => 517_500, 'captured_by' => $this->buyer->id]);
        $i->forceFill(['status' => 'approved'])->save();
    });

    // By 12 March the first activity should be finished (planned R500 000); 80% is done (earned R400 000) for R450 000 spent.
    $m = inCompany($this->company, fn () => app(EarnedValueService::class)->measure($this->project, Carbon::parse('2027-03-12')));
    expect($m['bac'])->toBe(1_000_000.0)->and($m['pv'])->toBe(500_000.0)->and($m['ev'])->toBe(400_000.0)->and($m['ac'])->toBe(450_000.0)
        ->and($m['spi'])->toBe(0.8)->and($m['cpi'])->toBe(0.89);
});

it('forecasts profit from the feasibility and flags cost codes over budget', function (): void {
    inCompany($this->company, function (): void {
        $service = app(FeasibilityService::class);
        $f = $service->create($this->project, 'Base', 12, null);
        $service->saveLines($f, [
            ['category' => 'construction', 'description' => 'Building contract', 'basis' => 'amount', 'amount' => 7_000_000, 'rate' => null, 'start_month' => 1, 'end_month' => 10],
            ['category' => 'revenue', 'description' => 'Sales', 'basis' => 'amount', 'amount' => 10_000_000, 'rate' => null, 'start_month' => 11, 'end_month' => 12],
        ], 12, null);
        $service->approve($f->fresh(), $this->director);
        app(BudgetService::class)->fromFeasibility($this->project);
        $line = BudgetLine::query()->firstOrFail();
        $supplier = compliantSupplier($this->company, 'Zulu Construction', 'contractor');
        $po = PurchaseOrder::query()->create(['project_id' => $this->project->id, 'supplier_id' => $supplier->id, 'budget_line_id' => $line->id, 'number' => 1, 'status' => 'approved', 'vat_applies' => true, 'created_by' => $this->buyer->id]);
        $po->forceFill(['subtotal' => 7_600_000])->save();
    });

    $p = inCompany($this->company, fn () => app(ProfitabilityService::class)->forProject($this->project));
    expect($p['baseline']['profit'])->toBe(3_000_000.0)->and($p['forecast']['cost'])->toBe(7_600_000.0)
        ->and($p['forecast']['profit'])->toBe(2_400_000.0)->and($p['overruns'][0]['over'])->toBe(600_000.0);
});

it('puts allowances in the payroll export and keeps employment documents private', function (): void {
    $employee = inCompany($this->company, fn () => Employee::query()->create(['employee_number' => 'E1', 'first_name' => 'Themba', 'last_name' => 'Khumalo', 'employment_type' => 'permanent', 'start_date' => '2026-01-05', 'days_per_week' => 5]));
    $siteManager = userWithRole($this->company, Role::SiteManager);

    $this->actingAs($siteManager)->post("/workforce/{$employee->ulid}/allowances", ['type' => 'travel', 'amount' => 50, 'frequency' => 'day', 'from_date' => '2027-01-01'])->assertSessionHas('success');
    inCompany($this->company, function () use ($employee, $siteManager): void {
        foreach (['2027-01-11', '2027-01-12', '2027-01-13'] as $day) {
            CrewAttendance::query()->create(['project_id' => $this->project->id, 'employee_id' => $employee->id, 'client_id' => (string) Str::uuid(), 'worked_on' => $day, 'status' => 'present', 'recorded_by' => $siteManager->id]);
        }
    });
    $csv = inCompany($this->company, fn () => app(ExportService::class)->payrollInputs(Carbon::parse('2027-01-01'), Carbon::parse('2027-01-31')));
    expect($csv)->toContain('Travel allowance (R50.00 per day)')->toContain(',3,');

    $this->actingAs($siteManager)->post("/workforce/{$employee->ulid}/documents", ['type' => 'contract', 'file' => UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf')])->assertSessionHas('success');
    $doc = inCompany($this->company, fn () => Document::query()->with('latestVersion')->firstOrFail());
    $url = "/documents/{$doc->ulid}/versions/{$doc->latestVersion->id}/download";
    $this->actingAs($siteManager)->get($url)->assertNotFound();
    $this->actingAs($this->director)->get($url)->assertOk();
});

it('applies POPIA retention: removes old selfies and register rows, anonymises former employees', function (): void {
    $siteManager = userWithRole($this->company, Role::SiteManager);
    inCompany($this->company, function () use ($siteManager): void {
        Storage::disk('documents')->put('selfie.jpg', 'x');
        SiteAttendance::query()->create(['project_id' => $this->project->id, 'user_id' => $siteManager->id, 'client_id' => (string) Str::uuid(), 'direction' => 'in', 'captured_at' => now()->subMonths(13), 'selfie_path' => 'selfie.jpg']);
        $old = Employee::query()->create(['employee_number' => 'E9', 'first_name' => 'Sipho', 'last_name' => 'Dlamini', 'id_number' => '8001015009087', 'employment_type' => 'temporary', 'start_date' => '2020-01-01', 'end_date' => now()->subMonths(40)->toDateString(), 'days_per_week' => 5]);
        $old->forceFill(['status' => 'left'])->save();
        CrewAttendance::query()->create(['project_id' => $this->project->id, 'employee_id' => $old->id, 'client_id' => (string) Str::uuid(), 'worked_on' => now()->subMonths(40)->toDateString(), 'status' => 'present', 'recorded_by' => $siteManager->id]);
    });

    $done = inCompany($this->company, fn () => app(RetentionService::class)->runForCurrentCompany());
    expect($done['attendance_selfies'])->toBe(1)->and($done['crew_attendance'])->toBe(1)->and($done['former_employees'])->toBe(1);
    Storage::disk('documents')->assertMissing('selfie.jpg');

    $old = inCompany($this->company, fn () => Employee::query()->where('employee_number', 'E9')->firstOrFail());
    expect($old->first_name)->toBe('Former')->and($old->id_number)->toBeNull();

    // Crew records must be kept at least 36 months (BCEA).
    $this->actingAs($this->admin)->patch('/settings/popia/retention/crew_attendance', ['months' => 12])->assertSessionHasErrors('months');
});

it('tracks data subject requests with a 30-day due date and exports what is held', function (): void {
    $employee = inCompany($this->company, function () {
        $e = Employee::query()->create(['employee_number' => 'E3', 'first_name' => 'Lindiwe', 'last_name' => 'Mthembu', 'employment_type' => 'permanent', 'start_date' => '2025-02-01', 'days_per_week' => 5]);
        LeaveRequest::query()->create(['employee_id' => $e->id, 'type' => 'annual', 'from_date' => '2026-06-01', 'to_date' => '2026-06-05', 'days' => 5, 'status' => 'approved']);

        return $e;
    });

    $this->actingAs($this->pm)->get('/settings/popia')->assertForbidden();
    $this->actingAs($this->admin)->post('/settings/popia/requests', [
        'requester_name' => 'Lindiwe Mthembu', 'type' => 'access', 'subject_type' => 'employee', 'subject' => $employee->ulid, 'received_on' => now()->subDays(5)->toDateString(),
    ])->assertSessionHas('success');

    $request = inCompany($this->company, fn () => DataSubjectRequest::query()->firstOrFail());
    expect($request->due_on->toDateString())->toBe(now()->subDays(5)->addDays(30)->toDateString());

    $this->actingAs($this->admin)->get("/settings/popia/requests/{$request->ulid}/export")->assertOk()
        ->assertJsonPath('person.name', 'Lindiwe Mthembu')->assertJsonPath('leave.0.type', 'annual');

    $this->actingAs($this->admin)->patch("/settings/popia/requests/{$request->ulid}", ['status' => 'completed'])->assertSessionHasErrors('outcome');
    $this->actingAs($this->admin)->patch("/settings/popia/requests/{$request->ulid}", ['status' => 'completed', 'outcome' => 'Sent a copy by email on 5 Sept'])->assertSessionHas('success');
});
