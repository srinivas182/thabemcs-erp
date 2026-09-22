<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Services\ApprovalEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->buyer = userWithRole($this->company, Role::Procurement);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->dm = userWithRole($this->company, Role::DevelopmentManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());
});

function raiseRequisition($test): Requisition
{
    $test->actingAs($test->siteManager)->post('/requisitions', [
        'project' => $test->project->ulid, 'title' => 'Cement and stone for Block C slab', 'submit' => true,
        'lines' => [
            ['description' => 'Cement 42.5N 50 kg', 'quantity' => 400, 'unit' => 'bag', 'estimated_unit_price' => 120],
            ['description' => '19 mm stone', 'quantity' => 60, 'unit' => 'm³', 'estimated_unit_price' => 900],
        ],
    ])->assertRedirect();

    return inCompany($test->company, fn () => Requisition::query()->firstOrFail());
}

function approveCurrent($test, $user, ?string $comment = null)
{
    $request = inCompany($test->company, fn () => ApprovalRequest::query()->where('status', 'pending')->latest('id')->firstOrFail());

    return $test->actingAs($user)->post("/inbox/{$request->ulid}", ['decision' => 'approve', 'comment' => $comment]);
}

it('uses the delegation of authority bands for purchase orders', function (): void {
    $engine = app(ApprovalEngine::class);

    expect($engine->stepsFor('purchase_order', 20_000))->toBe(['project-manager'])
        ->and($engine->stepsFor('purchase_order', 120_000))->toBe(['project-manager', 'finance', 'development-manager'])
        ->and($engine->stepsFor('purchase_order', 5_000_000))->toBe(['project-manager', 'finance', 'director', 'director']);
});

it('runs requisition to goods received with approvals, quotes, award and receiving', function (): void {
    $requisition = raiseRequisition($this);
    expect((float) $requisition->estimated_total)->toBe(102_000.0)->and($requisition->status)->toBe('submitted');

    // The requester cannot approve their own requisition; the project manager can.
    approveCurrent($this, $this->siteManager)->assertSessionHas('error', 'You cannot approve your own request.');
    approveCurrent($this, $this->pm)->assertSessionHas('success');
    expect($requisition->fresh()->status)->toBe('approved');

    // Three compliant quotes.
    foreach (['Build It Ballito' => 98_000, 'Cashbuild Stanger' => 95_000, 'Umhlali Hardware' => 101_000] as $name => $amount) {
        $supplier = compliantSupplier($this->company, $name);
        $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/quotes", ['supplier' => $supplier->ulid, 'amount' => $amount])->assertSessionHas('success');
    }

    $buildIt = inCompany($this->company, fn () => RequisitionQuote::query()->where('amount', 98_000)->firstOrFail());
    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/award", ['quote' => $buildIt->id])
        ->assertSessionHas('error', 'This is not the lowest quote. Record why it was chosen.');
    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/award", ['quote' => $buildIt->id, 'reason' => 'Can deliver in 2 days; others need 10'])
        ->assertRedirect();

    $order = inCompany($this->company, fn () => PurchaseOrder::query()->firstOrFail());
    // Line prices are spread from the quote; rounding to cents can leave a few cents' difference.
    expect((float) $order->subtotal)->toEqualWithDelta(98_000.0, 1.0)
        ->and((float) $order->vat)->toEqualWithDelta(14_700.0, 0.5)
        ->and($order->vat_applies)->toBeTrue();

    // R98 000 needs project manager, finance, then development manager.
    $this->actingAs($this->buyer)->post("/purchase-orders/{$order->ulid}/submit")->assertSessionHas('success');
    approveCurrent($this, $this->finance)->assertSessionHas('error');
    approveCurrent($this, $this->pm)->assertSessionHas('success');
    approveCurrent($this, $this->finance)->assertSessionHas('success');
    approveCurrent($this, $this->dm)->assertSessionHas('success');
    expect($order->fresh()->status)->toBe('approved');

    $this->actingAs($this->buyer)->post("/purchase-orders/{$order->ulid}/issue")->assertSessionHas('success');

    $lines = $order->lines()->get();
    $this->actingAs($this->siteManager)->post("/purchase-orders/{$order->ulid}/receive", [
        'received_on' => now()->toDateString(), 'quantities' => [$lines[0]->id => 400, $lines[1]->id => 20],
    ])->assertSessionHas('success');
    expect($order->fresh()->status)->toBe('partially_received');

    $this->actingAs($this->siteManager)->post("/purchase-orders/{$order->ulid}/receive", [
        'received_on' => now()->toDateString(), 'quantities' => [$lines[1]->id => 50],
    ])->assertSessionHas('error');
    $this->actingAs($this->siteManager)->post("/purchase-orders/{$order->ulid}/receive", [
        'received_on' => now()->toDateString(), 'quantities' => [$lines[1]->id => 40],
    ])->assertSessionHas('success');
    expect($order->fresh()->status)->toBe('received');
});

it('requires three quotes above the threshold unless single-sourced', function (): void {
    $requisition = raiseRequisition($this);
    approveCurrent($this, $this->pm);
    $supplier = compliantSupplier($this->company, 'Only Crane Hire', 'plant_hire');
    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/quotes", ['supplier' => $supplier->ulid, 'amount' => 90_000]);
    $quote = inCompany($this->company, fn () => RequisitionQuote::query()->firstOrFail());

    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/award", ['quote' => $quote->id])->assertSessionHas('error');
    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/award", ['quote' => $quote->id, 'single_source_reason' => 'Only 80 t crane available in the district'])->assertRedirect();
});

it('refuses to award to a non-compliant supplier', function (): void {
    $requisition = raiseRequisition($this);
    approveCurrent($this, $this->pm);
    $supplier = inCompany($this->company, fn () => Supplier::query()->create(['name' => 'No Papers CC', 'type' => 'supplier']));
    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/quotes", ['supplier' => $supplier->ulid, 'amount' => 20_000]);
    $quote = inCompany($this->company, fn () => RequisitionQuote::query()->firstOrFail());

    $this->actingAs($this->buyer)->post("/requisitions/{$requisition->ulid}/award", ['quote' => $quote->id])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not compliant'));
});

it('lets a delegate approve while the approver is away and shows it in their inbox', function (): void {
    $colleague = userWithRole($this->company, Role::QuantitySurveyor);
    $this->actingAs($this->pm)->post('/delegations', [
        'delegate' => $colleague->ulid, 'starts_on' => now('Africa/Johannesburg')->toDateString(), 'ends_on' => now()->addDays(7)->toDateString(),
    ])->assertSessionHas('success');

    raiseRequisition($this);

    $this->actingAs($colleague)->get('/inbox')->assertInertia(fn (Assert $page) => $page->component('inbox')->has('waiting', 1));
    approveCurrent($this, $colleague)->assertSessionHas('success');

    $step = inCompany($this->company, fn () => ApprovalRequest::query()->firstOrFail()->steps()->firstOrFail());
    expect($step->decided_by)->toBe($colleague->id)->and($step->on_behalf_of)->toBe($this->pm->id);
});

it('requires a reason to reject and tells the requester', function (): void {
    raiseRequisition($this);
    $this->actingAs($this->pm)->post('/inbox/'.inCompany($this->company, fn () => ApprovalRequest::query()->firstOrFail())->ulid, ['decision' => 'reject'])
        ->assertSessionHas('error', 'Give a reason for rejecting.');

    approveCurrent($this, $this->pm);
});

it('escalates approvals that wait too long', function (): void {
    $director = userWithRole($this->company, Role::Director);
    raiseRequisition($this);
    Notification::fake();
    $this->travel(49)->hours();

    $count = inCompany($this->company, fn () => app(ApprovalEngine::class)->escalateOverdue($this->company->id));

    expect($count)->toBe(1);
    Notification::assertSentTo($director, SystemMessage::class);
});
