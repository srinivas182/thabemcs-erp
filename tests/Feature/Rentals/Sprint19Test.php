<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Models\Project;
use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\LeaseInvoice;
use App\Domains\Rentals\Models\MaintenanceRequest;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Rentals\Services\LeaseService;
use App\Domains\Rentals\Services\RentBillingService;
use App\Domains\Sales\Models\SaleUnit;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->letting = userWithRole($this->company, Role::SalesAndLeasing);
    $this->director = userWithRole($this->company, Role::Director);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'BH-01']));
});

function rentalUnit($test, string $reference = 'Unit 3A'): SaleUnit
{
    return inCompany($test->company, fn () => SaleUnit::query()->create([
        'project_id' => $test->project->id, 'reference' => $reference, 'type' => 'sectional_unit', 'list_price' => 0, 'tenure' => 'rental', 'market_rent' => 12_000,
    ]));
}

function tenant($test, string $name = 'Sipho Ndlovu'): Tenant
{
    return inCompany($test->company, fn () => Tenant::query()->create(['name' => $name, 'entity_type' => 'individual', 'status' => 'approved', 'fica_verified' => true, 'credit_checked' => true]));
}

function activeLease($test, ?SaleUnit $unit = null, float $rent = 12_000, float $escalation = 8): Lease
{
    $unit ??= rentalUnit($test);
    $test->actingAs($test->letting)->post("/rentals/units/{$unit->ulid}/lease", [
        'tenant' => tenant($test, 'Tenant '.Str::random(4))->ulid, 'type' => 'residential', 'starts_on' => '2027-05-01', 'ends_on' => '2029-04-30',
        'rent_amount' => $rent, 'escalation_percent' => $escalation, 'payment_day' => 1, 'deposit_amount' => $rent, 'deposit_account' => 'FNB interest-bearing',
        'deposit_received_on' => '2027-04-25',
    ]);
    $lease = inCompany($test->company, fn () => Lease::query()->where('sale_unit_id', $unit->id)->sole());
    $test->actingAs($test->letting)->post("/rentals/leases/{$lease->ulid}/activate");

    return $lease->fresh();
}

it('creates a lease with its rent charge and a private tenant link', function (): void {
    $unit = rentalUnit($this);
    $tenant = tenant($this);

    $this->actingAs($this->letting)->post("/rentals/units/{$unit->ulid}/lease", [
        'tenant' => $tenant->ulid, 'type' => 'residential', 'starts_on' => '2027-05-01', 'ends_on' => '2028-04-30',
        'rent_amount' => 12_000, 'escalation_percent' => 8, 'payment_day' => 1, 'deposit_amount' => 12_000,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', fn (?string $m) => str_contains((string) $m, '/tenant/'));

    $lease = inCompany($this->company, fn () => Lease::query()->with('charges')->sole());
    expect($lease->reference())->toBe('L-0001')->and($lease->status)->toBe('draft')
        ->and($lease->charges)->toHaveCount(1)->and($lease->vat_applies)->toBeFalse()
        // The Consumer Protection Act gives a residential tenant 20 business days to cancel.
        ->and($lease->notice_days)->toBe(20)
        ->and($unit->fresh()->tenure)->toBe('rental');

    // A unit cannot be let twice at the same time.
    $this->actingAs($this->letting)->post("/rentals/leases/{$lease->ulid}/activate");
    $this->actingAs($this->letting)->post("/rentals/units/{$unit->ulid}/lease", [
        'tenant' => tenant($this, 'Someone Else')->ulid, 'type' => 'residential', 'starts_on' => '2027-06-01', 'rent_amount' => 9_000, 'payment_day' => 1,
    ])->assertSessionHas('error', fn (string $m) => str_contains($m, 'already has an active lease'));
});

it('invoices rent monthly and applies the escalation on the anniversary', function (): void {
    $lease = activeLease($this, null, 12_000, 8);

    $first = inCompany($this->company, fn () => app(RentBillingService::class)->invoice($lease, Carbon::parse('2027-06-01')));
    expect((float) $first->total)->toBe(12_000.0)->and($first->reference())->toBe('RI-00001');

    // Billing the same month twice is refused.
    expect(inCompany($this->company, fn () => app(RentBillingService::class)->invoice($lease, Carbon::parse('2027-06-15'))))->toBeNull();

    // A year in, rent escalates by 8%.
    $later = inCompany($this->company, fn () => app(RentBillingService::class)->invoice($lease, Carbon::parse('2028-06-01')));
    expect((float) $later->total)->toBe(12_960.0)->and($later->lines[0]['description'])->toContain('after 1 escalation');

    // Commercial leases carry VAT; residential letting is exempt.
    expect((float) $first->vat)->toBe(0.0);
});

it('allocates receipts to the oldest invoices and ages the arrears', function (): void {
    $lease = activeLease($this, null, 10_000, 0);
    $billing = fn (string $month) => inCompany($this->company, fn () => app(RentBillingService::class)->invoice($lease, Carbon::parse($month)));
    $billing('2027-05-01');
    $billing('2027-06-01');
    $billing('2027-07-01');

    $this->actingAs($this->letting)->post("/rentals/leases/{$lease->ulid}/receipts", [
        'amount' => 15_000, 'received_on' => '2027-07-05', 'method' => 'eft', 'reference' => 'Ndlovu rent',
    ])->assertSessionHas('success');

    $invoices = inCompany($this->company, fn () => LeaseInvoice::query()->orderBy('period_start')->get());
    expect($invoices[0]->status)->toBe('paid')->and($invoices[1]->status)->toBe('part_paid')
        ->and($invoices[1]->outstanding())->toBe(5_000.0)->and($invoices[2]->status)->toBe('issued');

    // On 5 August: May is over 30 days late, June is 1-30 days, July's is due on the 1st.
    $arrears = inCompany($this->company, fn () => app(LeaseService::class)->arrears($lease, Carbon::parse('2027-08-05')));
    expect($arrears['total'])->toBe(15_000.0)->and($arrears['days30'])->toBe(5_000.0)->and($arrears['days60'])->toBe(10_000.0);

    $this->actingAs($this->director)->get('/reports/rental-arrears')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.rows.0.total', fn ($v): bool => (float) $v > 0));
});

it('works out deposit interest and what must be refunded when the lease ends', function (): void {
    $lease = activeLease($this, null, 12_000, 0);

    // A year in an interest-bearing account at the configured rate.
    config(['rentals.deposit_interest_rate' => 5.0]);
    inCompany($this->company, fn () => app(RentBillingService::class)->accrueDepositInterest(Carbon::parse('2028-04-25')));
    $lease->refresh();
    expect((float) $lease->deposit_interest)->toBeGreaterThan(590.0)->toBeLessThan(610.0);

    $this->actingAs($this->letting)->post("/rentals/leases/{$lease->ulid}/end", [
        'ended_on' => '2029-04-30', 'end_reason' => 'Lease expired', 'deposit_deductions' => 1_500,
    ])->assertSessionHas('success');

    $lease->refresh();
    expect($lease->status)->toBe('ended')->and($lease->tenant->fresh()->status)->toBe('former')
        ->and(inCompany($this->company, fn () => app(LeaseService::class)->depositRefundDue($lease)))
        ->toBe(round(12_000 + (float) $lease->deposit_interest - 1_500, 2));
});

it('lets a tenant report a problem through their own link without signing in', function (): void {
    Notification::fake();
    $unit = rentalUnit($this, 'Unit 7B');
    $this->actingAs($this->letting)->post("/rentals/units/{$unit->ulid}/lease", [
        'tenant' => tenant($this)->ulid, 'type' => 'residential', 'starts_on' => '2027-05-01', 'rent_amount' => 9_500, 'payment_day' => 1,
    ]);
    $message = (string) session('success');
    $token = basename(str_replace('.', '', explode('/tenant/', $message)[1] ?? ''));
    $token = substr($token, 0, 48);

    auth()->logout();
    $this->get("/tenant/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('rentals/tenant-portal')->where('lease.unit', 'Unit 7B'));

    $this->post("/tenant/{$token}/requests", ['category' => 'plumbing', 'description' => 'Geyser leaking into the ceiling', 'priority' => 'urgent'])
        ->assertSessionHas('success');

    $request = inCompany($this->company, fn () => MaintenanceRequest::query()->sole());
    expect($request->reported_by_tenant)->toBeTrue()->and($request->priority)->toBe('urgent')->and($request->reference())->toBe('MR-0001');
    Notification::assertSentTo($this->letting, SystemMessage::class);

    $this->get('/tenant/'.Str::random(48))->assertNotFound();
});

it('keeps rentals away from people who do not work in letting', function (): void {
    $lease = activeLease($this);
    $this->actingAs($this->siteManager)->get('/rentals')->assertForbidden();
    $this->actingAs($this->siteManager)->post("/rentals/leases/{$lease->ulid}/receipts", ['amount' => 100, 'received_on' => now()->toDateString(), 'method' => 'eft'])->assertForbidden();
    $this->actingAs($this->letting)->get('/rentals')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('summary.active', 1));
    $this->actingAs($this->director)->get('/reports/rent-roll')->assertOk();
});
