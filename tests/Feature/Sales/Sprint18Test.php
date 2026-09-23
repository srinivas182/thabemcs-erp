<?php

declare(strict_types=1);

use App\Domains\Finance\Services\ProfitabilityService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\Buyer;
use App\Domains\Sales\Models\Reservation;
use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleCondition;
use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Sales\Services\SalesService;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->sales = userWithRole($this->company, Role::SalesAndLeasing);
    $this->director = userWithRole($this->company, Role::Director);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'BH-01', 'name' => 'Ballito Heights']));
});

function unit($test, string $reference, float $price = 1_495_000, string $type = 'house'): SaleUnit
{
    return inCompany($test->company, fn () => SaleUnit::query()->create([
        'project_id' => $test->project->id, 'reference' => $reference, 'type' => $type, 'list_price' => $price, 'vat_applies' => true, 'nhbrc_enrolment' => 'NH-1234',
    ]));
}

function buyer($test, string $name = 'Nomsa Dlamini'): Buyer
{
    return inCompany($test->company, fn () => Buyer::query()->create(['name' => $name, 'entity_type' => 'individual', 'status' => 'qualified', 'fica_verified' => true]));
}

it('holds a unit on reservation and releases it when the reservation runs out', function (): void {
    Notification::fake();
    $unit = unit($this, 'Erf 12');
    $buyer = buyer($this);

    $this->actingAs($this->sales)->post("/sales/units/{$unit->ulid}/reserve", [
        'buyer' => $buyer->ulid, 'reserved_on' => now()->subDays(20)->toDateString(), 'expires_on' => now()->subDay()->toDateString(), 'deposit_amount' => 25_000,
    ])->assertSessionHas('success');
    expect($unit->fresh()->status)->toBe('reserved');

    // Another buyer cannot take a reserved unit.
    $this->actingAs($this->sales)->post("/sales/units/{$unit->ulid}/reserve", [
        'buyer' => buyer($this, 'Second Buyer')->ulid, 'reserved_on' => now()->toDateString(),
    ])->assertSessionHas('error', fn (string $m) => str_contains($m, 'reserved'));

    $released = inCompany($this->company, fn () => app(SalesService::class)->releaseExpiredReservations());
    expect($released)->toBe(1)->and($unit->fresh()->status)->toBe('available')
        ->and(inCompany($this->company, fn () => Reservation::query()->first()->status))->toBe('expired');
    Notification::assertSentTo($this->sales, SystemMessage::class);
});

it('records a sale with its conditions and transfer pipeline, and goes unconditional when they are met', function (): void {
    $unit = unit($this, 'Erf 14', 1_725_000);
    $buyer = buyer($this);

    $this->actingAs($this->sales)->post("/sales/units/{$unit->ulid}/sign", [
        'buyer' => $buyer->ulid, 'signed_on' => now()->toDateString(), 'purchase_price' => 1_725_000, 'vat_applies' => true,
        'deposit_amount' => 100_000, 'deposit_held_by' => 'Shange Attorneys Trust', 'bond_required' => true, 'bond_amount' => 1_625_000,
        'commission_percent' => 5, 'conditions' => [['type' => 'bond_approval'], ['type' => 'deposit']],
    ])->assertRedirect();

    $agreement = inCompany($this->company, fn () => SaleAgreement::query()->with(['conditions', 'steps'])->sole());
    expect($agreement->reference())->toBe('SA-0001')->and($agreement->status)->toBe('conditional')
        ->and($agreement->conditions)->toHaveCount(2)->and($agreement->steps)->toHaveCount(9)
        ->and($unit->fresh()->status)->toBe('sold')
        // Commission is 5% of the price excluding VAT: 1 725 000 / 1.15 = 1 500 000.
        ->and((float) $agreement->commission_amount)->toBe(75_000.0)
        ->and($agreement->netPrice())->toBe(1_500_000.0);

    // The bond condition is due 30 days after signature by default.
    $bond = $agreement->conditions->firstWhere('type', 'bond_approval');
    expect($bond->due_on->toDateString())->toBe(now()->addDays(30)->toDateString());

    foreach ($agreement->conditions as $condition) {
        $this->actingAs($this->sales)->patch("/sales/conditions/{$condition->id}", ['status' => 'met'])->assertSessionHas('success');
    }
    expect($agreement->fresh()->status)->toBe('unconditional');
});

it('lapses the sale and frees the unit when a condition fails', function (): void {
    $unit = unit($this, 'Erf 16');
    $buyer = buyer($this);
    $this->actingAs($this->sales)->post("/sales/units/{$unit->ulid}/sign", [
        'buyer' => $buyer->ulid, 'signed_on' => now()->toDateString(), 'purchase_price' => 1_495_000,
        'conditions' => [['type' => 'bond_approval']],
    ]);
    $condition = inCompany($this->company, fn () => SaleCondition::query()->sole());

    $this->actingAs($this->sales)->patch("/sales/conditions/{$condition->id}", ['status' => 'failed', 'notes' => 'Bond declined by the bank'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'lapsed'));

    expect(inCompany($this->company, fn () => SaleAgreement::query()->sole()->status))->toBe('lapsed')
        ->and($unit->fresh()->status)->toBe('available')
        ->and($buyer->fresh()->status)->toBe('qualified');
});

it('transfers the unit on registration and pays commission only to a compliant agency', function (): void {
    $agency = inCompany($this->company, function () {
        $agency = Supplier::query()->create(['name' => 'Coastal Properties', 'type' => 'estate_agency']);
        foreach (['cipc', 'tax_compliance', 'bank_confirmation'] as $doc) {
            SupplierDocument::query()->create(['supplier_id' => $agency->id, 'type' => $doc, 'expires_on' => now()->addYear()->toDateString()]);
        }

        return $agency;
    });
    $unit = unit($this, 'Erf 18');
    $this->actingAs($this->sales)->post("/sales/units/{$unit->ulid}/sign", [
        'buyer' => buyer($this)->ulid, 'signed_on' => now()->toDateString(), 'purchase_price' => 2_300_000,
        'agent_supplier' => $agency->ulid, 'commission_percent' => 5, 'conditions' => [],
    ]);
    $agreement = inCompany($this->company, fn () => SaleAgreement::query()->with('steps')->sole());
    expect($agreement->status)->toBe('unconditional');

    $registration = $agreement->steps->firstWhere('step', 'registration');
    $this->actingAs($this->sales)->patch("/sales/transfer-steps/{$registration->id}", ['completed_on' => now()->toDateString()])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'registered'));
    expect($agreement->fresh()->status)->toBe('registered')->and($unit->fresh()->status)->toBe('transferred');

    // The Fidelity Fund Certificate is missing, so commission cannot be approved.
    $this->actingAs($this->director)->post("/sales/agreements/{$agreement->ulid}/commission")
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Fidelity Fund Certificate'));

    inCompany($this->company, fn () => SupplierDocument::query()->create(['supplier_id' => $agency->id, 'type' => 'ffc', 'expires_on' => now()->addMonths(6)->toDateString()]));
    $this->actingAs($this->director)->post("/sales/agreements/{$agreement->ulid}/commission")->assertSessionHas('success');
    expect($agreement->fresh()->commission_status)->toBe('approved');
});

it('uses sales instead of the feasibility for revenue once units are on sale', function (): void {
    $sold = unit($this, 'Erf 20', 1_150_000);
    unit($this, 'Erf 21', 2_300_000);
    $this->actingAs($this->sales)->post("/sales/units/{$sold->ulid}/sign", [
        'buyer' => buyer($this)->ulid, 'signed_on' => now()->toDateString(), 'purchase_price' => 1_150_000, 'conditions' => [],
    ]);

    $profit = inCompany($this->company, fn () => app(ProfitabilityService::class)->forProject($this->project));
    // Sold at 1 150 000 incl. VAT (1 000 000 net) plus one unit still listed at 2 300 000 (2 000 000 net).
    expect($profit['revenueSource'])->toBe('sales')->and($profit['forecast']['revenue'])->toBe(3_000_000.0)
        ->and($profit['sales']['contracted'])->toBe(1_000_000.0)->and($profit['sales']['earned'])->toBe(0.0);

    $this->actingAs($this->director)->get('/reports/sales-schedule')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('result.rows', 2)->where('result.rows.0.unit', 'Erf 20'));
});

it('keeps sales away from people who do not work in sales', function (): void {
    $unit = unit($this, 'Erf 22');
    $this->actingAs($this->siteManager)->get("/projects/{$this->project->ulid}/sales")->assertForbidden();
    $this->actingAs($this->siteManager)->post("/sales/units/{$unit->ulid}/withdraw")->assertForbidden();
    $this->actingAs($this->sales)->get('/sales/buyers')->assertOk();
});
