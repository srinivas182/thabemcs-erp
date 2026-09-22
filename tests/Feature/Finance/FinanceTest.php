<?php

declare(strict_types=1);

use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\PaymentRun;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Models\VariationOrder;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Domains\Workflow\Models\ApprovalRequest;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->finance2 = userWithRole($this->company, Role::Finance);
    $this->director = userWithRole($this->company, Role::Director);
    $this->qs = userWithRole($this->company, Role::QuantitySurveyor);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['project_manager_id' => $this->pm->id]));
    $this->supplier = compliantSupplier($this->company, 'Ndlovu Builders Supplies');
});

/** An issued purchase order of R100 000 (400 bags at R250) on a cost code with R120 000 budget. */
function issuedOrder($test, float $receivedBags = 400): PurchaseOrder
{
    return inCompany($test->company, function () use ($test, $receivedBags) {
        $line = BudgetLine::query()->create(['project_id' => $test->project->id, 'code' => '05.01', 'description' => 'Concrete works', 'original_amount' => 120_000]);
        $order = PurchaseOrder::query()->create(['project_id' => $test->project->id, 'supplier_id' => $test->supplier->id, 'budget_line_id' => $line->id, 'number' => 1, 'status' => 'issued', 'vat_applies' => true, 'created_by' => $test->pm->id]);
        PurchaseOrderLine::query()->create(['purchase_order_id' => $order->id, 'description' => 'Cement', 'quantity' => 400, 'unit' => 'bag', 'unit_price' => 250, 'received_quantity' => $receivedBags]);
        $order->recalculate();

        return $order->fresh();
    });
}

function captureInvoice($test, PurchaseOrder $order, array $overrides = [])
{
    return $test->actingAs($test->finance)->post('/invoices', [
        'supplier' => $test->supplier->ulid, 'purchase_order' => $order->ulid, 'invoice_number' => 'INV-2041',
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 100_000, 'vat' => 15_000, 'total' => 115_000, ...$overrides,
    ]);
}

it('creates the budget from the approved feasibility using SA cost code headings', function (): void {
    inCompany($this->company, function (): void {
        $service = app(FeasibilityService::class);
        $f = $service->create($this->project, 'Base case', 18, null);
        $service->saveLines($f, [
            ['category' => 'land', 'description' => 'Land', 'basis' => 'amount', 'amount' => 8_000_000, 'rate' => null, 'start_month' => 1, 'end_month' => 1],
            ['category' => 'construction', 'description' => 'Building contract', 'basis' => 'amount', 'amount' => 30_000_000, 'rate' => null, 'start_month' => 3, 'end_month' => 15],
            ['category' => 'professional_fees', 'description' => 'Professional team', 'basis' => 'percent_of_construction', 'amount' => null, 'rate' => 12, 'start_month' => 1, 'end_month' => 15],
            ['category' => 'revenue', 'description' => 'Sales', 'basis' => 'amount', 'amount' => 55_000_000, 'rate' => null, 'start_month' => 16, 'end_month' => 18],
        ], 18, null);
        $service->approve($f->fresh(), $this->director);
    });

    $this->actingAs($this->qs)->post("/projects/{$this->project->ulid}/budget/from-feasibility")->assertSessionHas('success');

    $lines = inCompany($this->company, fn () => BudgetLine::query()->orderBy('code')->pluck('original_amount', 'code')->map(fn ($v) => (float) $v)->all());
    expect($lines)->toBe(['01.01' => 8_000_000.0, '03.01' => 3_600_000.0, '05.01' => 30_000_000.0]);
});

it('imports a bill of quantities from CSV, reading South African number formats', function (): void {
    $csv = "code,description,quantity,rate\n05.01,Excavation,120,\"R 350,00\"\n05.02,\"Concrete 25 MPa\",85,\"1 450.50\"\n,,,\n";
    $file = UploadedFile::fake()->createWithContent('boq.csv', $csv);

    $this->actingAs($this->qs)->post("/projects/{$this->project->ulid}/budget/import", ['file' => $file])->assertSessionHas('success', '2 cost codes imported.');

    $amounts = inCompany($this->company, fn () => BudgetLine::query()->orderBy('code')->pluck('original_amount')->map(fn ($v) => (float) $v)->all());
    expect($amounts)->toBe([42_000.0, 123_292.5]);
});

it('matches an invoice to the order and goods received, and stops the capturer approving it', function (): void {
    $order = issuedOrder($this);
    captureInvoice($this, $order)->assertSessionHas('success');

    $invoice = inCompany($this->company, fn () => SupplierInvoice::query()->firstOrFail());
    expect($invoice->status)->toBe('matched');

    $this->actingAs($this->finance)->post("/invoices/{$invoice->ulid}/approve")->assertSessionHas('error', 'The person who captured an invoice cannot also approve it.');
    $this->actingAs($this->finance2)->post("/invoices/{$invoice->ulid}/approve")->assertSessionHas('success');
    expect($invoice->fresh()->status)->toBe('approved');
});

it('flags invoices for more than was received, wrong VAT and duplicates', function (): void {
    $order = issuedOrder($this, receivedBags: 200);

    captureInvoice($this, $order, ['vat' => 14_000, 'total' => 114_000])->assertSessionHas('error');
    $invoice = inCompany($this->company, fn () => SupplierInvoice::query()->firstOrFail());
    expect($invoice->status)->toBe('exception')
        ->and(implode(' ', $invoice->match_issues))->toContain('VAT should be R15 000.00')->toContain('Only R50 000.00 of goods have been received');

    captureInvoice($this, $order)->assertSessionHasErrors('invoice_number');

    // Finance cannot override; a Director can, with a reason.
    $this->actingAs($this->finance2)->post("/invoices/{$invoice->ulid}/approve", ['override_reason' => 'x'])->assertSessionHas('error');
    $this->actingAs($this->director)->post("/invoices/{$invoice->ulid}/approve")->assertSessionHas('error');
    $this->actingAs($this->director)->post("/invoices/{$invoice->ulid}/approve", ['override_reason' => 'Balance delivered 3 Feb; GRN outstanding'])->assertSessionHas('success');
});

it('warns when committed spend crosses 80% of a cost code and adds approved variations to the budget', function (): void {
    Notification::fake();
    $order = issuedOrder($this);
    $line = inCompany($this->company, fn () => BudgetLine::query()->firstOrFail());

    inCompany($this->company, fn () => app(BudgetService::class)->checkThresholds($line));
    Notification::assertSentTo($this->pm, SystemMessage::class, fn (SystemMessage $m) => str_starts_with($m->title, 'Budget 80% used'));

    $this->actingAs($this->qs)->post("/projects/{$this->project->ulid}/variations", [
        'budget_line_id' => $line->id, 'title' => 'Extra footing depth', 'reason' => 'unforeseen', 'amount' => 30_000, 'time_impact_days' => 5,
    ])->assertSessionHas('success');

    $request = inCompany($this->company, fn () => ApprovalRequest::query()->where('policy', 'variation')->firstOrFail());
    $this->actingAs($this->pm)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('error'); // QS step first, and the QS raised it
    $qs2 = userWithRole($this->company, Role::QuantitySurveyor);
    $this->actingAs($qs2)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('success');
    $this->actingAs($this->pm)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('success');

    expect(inCompany($this->company, fn () => VariationOrder::query()->firstOrFail()->status))->toBe('approved');
    $this->actingAs($this->qs)->get("/projects/{$this->project->ulid}/budget")
        ->assertInertia(fn (Assert $page) => $page->component('projects/budget')->where('lines.0.revised', 150000)->where('lines.0.available', 50000));
});

it('builds a payment run without non-compliant suppliers, gets director approval and exports the schedule', function (): void {
    $order = issuedOrder($this);
    captureInvoice($this, $order);
    $invoice = inCompany($this->company, fn () => SupplierInvoice::query()->firstOrFail());
    $this->actingAs($this->finance2)->post("/invoices/{$invoice->ulid}/approve");

    // A second approved invoice from a supplier whose tax clearance has expired.
    $lapsed = compliantSupplier($this->company, 'Lapsed Plant Hire');
    inCompany($this->company, function () use ($lapsed): void {
        SupplierDocument::query()->where('supplier_id', $lapsed->id)->where('type', 'tax_compliance')->update(['expires_on' => now()->subDay()->toDateString()]);
        $i = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $lapsed->id, 'invoice_number' => 'LP-9', 'invoice_date' => now(), 'due_date' => now(), 'subtotal' => 1000, 'vat' => 150, 'total' => 1150, 'captured_by' => $this->finance->id]);
        $i->forceFill(['status' => 'approved'])->save();
    });

    $this->actingAs($this->finance)->post('/payment-runs', ['pay_on' => now()->addDays(31)->toDateString()])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Lapsed Plant Hire'));

    $run = inCompany($this->company, fn () => PaymentRun::query()->firstOrFail());
    expect((float) $run->total)->toBe(115_000.0);

    $this->actingAs($this->finance)->post("/payment-runs/{$run->ulid}/submit")->assertSessionHas('success');
    $request = inCompany($this->company, fn () => ApprovalRequest::query()->where('policy', 'payment_run')->firstOrFail());
    $this->actingAs($this->director)->post("/inbox/{$request->ulid}", ['decision' => 'approve'])->assertSessionHas('success');

    $csv = (string) $this->actingAs($this->finance)->get("/payment-runs/{$run->ulid}/export")->assertOk()->getContent();
    expect($csv)->toContain('Ndlovu Builders Supplies')->toContain('115000.00')->toContain('INV-2041');

    $this->actingAs($this->finance)->post("/payment-runs/{$run->ulid}/paid")->assertSessionHas('success');
    expect($invoice->fresh()->status)->toBe('paid');
});
