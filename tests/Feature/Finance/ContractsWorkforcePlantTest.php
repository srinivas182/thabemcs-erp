<?php

declare(strict_types=1);

use App\Domains\Contracts\Models\Contract;
use App\Domains\Contracts\Models\PaymentCertificate;
use App\Domains\Contracts\Services\CertificateService;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Plant\Models\PlantItem;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\OvertimeEntry;
use App\Domains\Workforce\Services\PublicHolidays;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->qs = userWithRole($this->company, Role::QuantitySurveyor);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->dm = userWithRole($this->company, Role::DevelopmentManager);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());
    $this->contractor = compliantSupplier($this->company, 'Zulu Construction', 'contractor');
    $this->contract = inCompany($this->company, fn () => Contract::query()->create([
        'project_id' => $this->project->id, 'supplier_id' => $this->contractor->id, 'reference' => 'BH-MAIN-01', 'contract_form' => 'jbcc_pba',
        'contract_sum' => 10_000_000, 'retention_percent' => 10, 'retention_cap_percent' => 5, 'release_at_practical_percent' => 50,
    ]));
});

function certify($test, float $gross, string $date): PaymentCertificate
{
    return inCompany($test->company, function () use ($test, $gross, $date) {
        $c = app(CertificateService::class)->prepare($test->contract->fresh(), $gross, Carbon::parse($date), null, $test->qs);
        $c->forceFill(['status' => 'certified'])->save();

        return $c;
    });
}

it('calculates retention with a limit and releases it at practical and final completion', function (): void {
    $pc1 = certify($this, 2_000_000, '2027-02-28');
    expect((float) $pc1->retention_held)->toBe(200_000.0)->and((float) $pc1->amount_due)->toBe(1_800_000.0)->and((float) $pc1->vat)->toBe(270_000.0);

    // Retention stops at 5% of R10m.
    $pc2 = certify($this, 6_000_000, '2027-03-31');
    expect((float) $pc2->retention_held)->toBe(500_000.0)->and((float) $pc2->amount_due)->toBe(3_700_000.0);

    // Half released at practical completion.
    $this->contract->update(['practical_completion_on' => '2027-06-15']);
    $pc3 = certify($this, 10_000_000, '2027-06-30');
    expect((float) $pc3->retention_released)->toBe(250_000.0)->and((float) $pc3->amount_due)->toBe(4_250_000.0);

    // The rest at final completion: everything paid out.
    $this->contract->update(['final_completion_on' => '2027-12-15']);
    $pc4 = certify($this, 10_000_000, '2027-12-31');
    expect((float) $pc4->amount_due)->toBe(250_000.0);

    $total = inCompany($this->company, fn () => (float) PaymentCertificate::query()->sum('amount_due'));
    expect($total)->toBe(10_000_000.0);
});

it('takes a certificate through approval and matches the contractor invoice to it', function (): void {
    $this->actingAs($this->qs)->post("/contracts/{$this->contract->ulid}/certificates", ['gross_value' => 500_000, 'valuation_date' => now()->toDateString()])->assertSessionHas('success');
    $certificate = inCompany($this->company, fn () => PaymentCertificate::query()->firstOrFail());

    $this->actingAs($this->qs)->post("/payment-certificates/{$certificate->ulid}/submit")->assertSessionHas('success');
    $request = inCompany($this->company, fn () => ApprovalRequest::query()->firstOrFail());
    $this->actingAs($this->pm)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('success');
    $this->actingAs($this->dm)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('success');
    expect($certificate->fresh()->status)->toBe('certified');

    $this->actingAs($this->finance)->post('/invoices', [
        'supplier' => $this->contractor->ulid, 'certificate' => $certificate->ulid, 'invoice_number' => 'ZC-PC1',
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 450_000, 'vat' => 67_500, 'total' => 517_500,
    ])->assertSessionHas('success');
    expect(inCompany($this->company, fn () => SupplierInvoice::query()->firstOrFail()->status))->toBe('matched');
});

it('knows South African public holidays, including Easter and the Sunday rule', function (): void {
    $h = new PublicHolidays;

    expect($h->forYear(2027))->toContain('2027-03-26', '2027-03-29', '2027-03-22', '2027-12-16')
        // Mon 22 Mar to Fri 2 Apr 2027: 10 weekdays less three public holidays.
        ->and($h->workingDays(Carbon::parse('2027-03-22'), Carbon::parse('2027-04-02')))->toBe(7);
});

it('keeps employee ID numbers encrypted and enforces BCEA leave and overtime limits', function (): void {
    $this->actingAs($this->siteManager)->post('/workforce', [
        'employee_number' => 'E001', 'first_name' => 'Themba', 'last_name' => 'Khumalo', 'id_number' => '8001015009087',
        'employment_type' => 'permanent', 'start_date' => '2026-01-05', 'days_per_week' => 5,
    ])->assertRedirect();
    $employee = inCompany($this->company, fn () => Employee::query()->firstOrFail());

    expect(DB::table('employees')->value('id_number'))->not->toContain('8001015009087')
        ->and($employee->maskedIdNumber())->toEndWith('9087');

    // 15 working days of annual leave: 16 is too many.
    $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/leave", ['type' => 'annual', 'from_date' => '2027-05-03', 'to_date' => '2027-05-24'])
        ->assertSessionHasErrors('to_date');
    $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/leave", ['type' => 'annual', 'from_date' => '2027-05-03', 'to_date' => '2027-05-07'])
        ->assertSessionHas('success');

    // Overtime: max 3 hours a day and 10 a week; Sunday is double time. Use last week (Monday to Sunday).
    $monday = now()->subWeek()->startOfWeek();
    [$mon, $tue, $wed, $thu, $sun] = [$monday->toDateString(), $monday->copy()->addDay()->toDateString(), $monday->copy()->addDays(2)->toDateString(), $monday->copy()->addDays(3)->toDateString(), $monday->copy()->addDays(6)->toDateString()];
    $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/overtime", ['worked_on' => $mon, 'hours' => 4])->assertSessionHasErrors('hours');
    foreach ([$mon, $tue, $wed] as $day) {
        $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/overtime", ['worked_on' => $day, 'hours' => 3])->assertSessionHas('success');
    }
    $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/overtime", ['worked_on' => $thu, 'hours' => 2])->assertSessionHasErrors('hours');
    $this->actingAs($this->siteManager)->post("/workforce/{$employee->ulid}/overtime", ['worked_on' => $sun, 'hours' => 1])->assertSessionHas('success');

    $sunday = inCompany($this->company, fn () => OvertimeEntry::query()->whereDate('worked_on', $sun)->firstOrFail());
    expect((float) $sunday->rate_multiplier)->toBe(2.0);
});

it('tracks plant movements and the next service date', function (): void {
    $this->actingAs($this->siteManager)->post('/plant', [
        'asset_number' => 'TLB-01', 'description' => 'TLB 4x4', 'category' => 'Earthmoving', 'ownership' => 'owned', 'service_interval_days' => 90,
    ])->assertSessionHas('success');
    $item = inCompany($this->company, fn () => PlantItem::query()->firstOrFail());

    $this->actingAs($this->siteManager)->post("/plant/{$item->ulid}/events", ['type' => 'moved', 'project' => $this->project->ulid, 'happened_on' => now()->toDateString()])->assertSessionHas('success');
    $this->actingAs($this->siteManager)->post("/plant/{$item->ulid}/events", ['type' => 'serviced', 'happened_on' => now()->toDateString(), 'cost' => 4_500])->assertSessionHas('success');

    $item->refresh();
    expect($item->status)->toBe('on_site')->and($item->project_id)->toBe($this->project->id)
        ->and($item->next_service_on?->toDateString())->toBe(now()->addDays(90)->toDateString());
});

it('exports approved supplier invoices for Sage', function (): void {
    inCompany($this->company, function (): void {
        $i = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $this->contractor->id, 'invoice_number' => 'ZC-77', 'invoice_date' => '2027-01-10', 'due_date' => '2027-02-10', 'subtotal' => 1000, 'vat' => 150, 'total' => 1150, 'captured_by' => $this->finance->id]);
        $i->forceFill(['status' => 'approved'])->save();
    });

    $csv = (string) $this->actingAs($this->finance)->get('/exports/supplier-invoices?from=2027-01-01&to=2027-01-31')->assertOk()->getContent();
    expect($csv)->toContain('Zulu Construction')->toContain('10/01/2027')->toContain('ZC-77')->toContain('1150.00');
});
