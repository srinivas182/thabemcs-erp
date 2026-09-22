<?php

declare(strict_types=1);

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Plant\Models\PlantEvent;
use App\Domains\Plant\Models\PlantItem;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Mail\ScheduledReport;
use App\Domains\Reporting\Models\ReportSchedule;
use App\Domains\Reporting\Reports\PlantUtilisationReport;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ScheduledReportSender;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['name' => 'Thabekhulu Developments']);
    $this->director = userWithRole($this->company, Role::Director);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'PRJ-0001', 'name' => 'Ballito Heights']));
    $this->supplier = compliantSupplier($this->company, 'Build It Ballito');

    inCompany($this->company, function (): void {
        $line = BudgetLine::query()->create(['project_id' => $this->project->id, 'code' => '05.01', 'description' => 'Concrete works', 'original_amount' => 200_000]);
        $order = PurchaseOrder::query()->create(['project_id' => $this->project->id, 'supplier_id' => $this->supplier->id, 'budget_line_id' => $line->id, 'number' => 1, 'status' => 'issued', 'vat_applies' => true, 'created_by' => $this->director->id]);
        PurchaseOrderLine::query()->create(['purchase_order_id' => $order->id, 'description' => 'Cement', 'quantity' => 400, 'unit' => 'bag', 'unit_price' => 250, 'received_quantity' => 200]);
        $order->recalculate();

        // One invoice 45 days overdue, one not yet due.
        foreach ([['A-1', now()->subDays(75), now()->subDays(45), 11_500], ['A-2', now()->subDays(5), now()->addDays(25), 5_750]] as [$no, $date, $due, $total]) {
            $i = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $this->supplier->id, 'purchase_order_id' => $order->id, 'invoice_number' => $no, 'invoice_date' => $date, 'due_date' => $due, 'subtotal' => $total / 1.15, 'vat' => $total - $total / 1.15, 'total' => $total, 'captured_by' => $this->finance->id]);
            $i->forceFill(['status' => 'approved'])->save();
        }
    });
});

it('shows the cost report with committed spend and totals', function (): void {
    $this->actingAs($this->director)->get('/reports/cost-report')
        ->assertInertia(fn (Assert $page) => $page->component('reports/show')
            ->where('result.rows.0.code', '05.01')
            ->where('result.rows.0.committed', 100000)
            ->where('result.rows.0.available', 100000)
            ->where('result.totals.revised', 200000));
});

it('ages what is owed to suppliers by days overdue', function (): void {
    $this->actingAs($this->finance)->get('/reports/supplier-age-analysis')
        ->assertInertia(fn (Assert $page) => $page->where('result.rows.0.supplier', 'Build It Ballito')
            ->where('result.rows.0.current', 5750)->where('result.rows.0.d60', 11500)->where('result.rows.0.total', 17250));
});

it('downloads a valid Excel file and a CSV', function (): void {
    $xlsx = (string) $this->actingAs($this->finance)->get('/reports/commitments/download/xlsx')->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->getContent();

    $path = tempnam(sys_get_temp_dir(), 't');
    file_put_contents((string) $path, $xlsx);
    $zip = new ZipArchive;
    expect($zip->open((string) $path))->toBeTrue();
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    expect($sheet)->toContain('Commitments')->toContain('PO-0001')->toContain('Build It Ballito');
    $zip->close();

    $csv = (string) $this->actingAs($this->finance)->get('/reports/commitments/download/csv')->assertOk()->getContent();
    expect($csv)->toContain('Still to invoice')->toContain('PO-0001');
});

it('only offers reports the user is allowed to see', function (): void {
    $this->actingAs($this->siteManager)->get('/reports/cost-report')->assertForbidden();
    $this->actingAs($this->siteManager)->get('/reports/safety-statistics')->assertOk();
    $this->actingAs($this->siteManager)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page->where('reports', fn ($r) => collect($r)->pluck('key')->contains('safety-statistics') && ! collect($r)->pluck('key')->contains('cost-report')));
});

it('counts plant days on site and hire cost from the movement history', function (): void {
    $result = inCompany($this->company, function () {
        $item = PlantItem::query()->create(['asset_number' => 'EX-01', 'description' => '20 t excavator', 'category' => 'Earthmoving', 'ownership' => 'hired', 'supplier_id' => $this->supplier->id, 'hire_rate_per_day' => 3_500]);
        PlantEvent::query()->create(['plant_item_id' => $item->id, 'type' => 'moved', 'project_id' => $this->project->id, 'happened_on' => '2027-01-11', 'recorded_by' => $this->director->id]);
        PlantEvent::query()->create(['plant_item_id' => $item->id, 'type' => 'breakdown', 'happened_on' => '2027-01-15', 'recorded_by' => $this->director->id]);
        PlantEvent::query()->create(['plant_item_id' => $item->id, 'type' => 'repaired', 'happened_on' => '2027-01-17', 'recorded_by' => $this->director->id]);
        PlantEvent::query()->create(['plant_item_id' => $item->id, 'type' => 'off_hired', 'happened_on' => '2027-01-21', 'recorded_by' => $this->director->id]);

        return app(PlantUtilisationReport::class)->build(ReportFilters::fromArray(['from' => '2027-01-01', 'to' => '2027-01-31']));
    });

    // On site 11 to 20 January (10 days), broken 15 and 16 January (2 days).
    expect($result->rows[0]['on_site'])->toBe(10)->and($result->rows[0]['broken'])->toBe(2)
        ->and($result->rows[0]['hire_cost'])->toBe(35_000.0)->and($result->rows[0]['utilisation'])->toBe(25.8);
});

it('shows directors the portfolio and the Super Admin the whole group', function (): void {
    $this->actingAs($this->director)->get('/dashboard/portfolio')
        ->assertInertia(fn (Assert $page) => $page->component('reports/dashboard')
            ->where('portfolio.projects.0.name', 'Ballito Heights')->where('portfolio.projects.0.used', 50)
            ->where('portfolio.totals.owedToSuppliers', 17250));

    $this->actingAs($this->siteManager)->get('/dashboard/portfolio')->assertForbidden();

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get('/dashboard/portfolio')
        ->assertInertia(fn (Assert $page) => $page->where('group', fn ($g) => collect($g)->contains(fn ($c) => $c['name'] === 'Thabekhulu Developments' && $c['projects'] === 1)));
});

it('emails scheduled reports once on their day, only to people allowed to see them', function (): void {
    Mail::fake();
    inCompany($this->company, fn () => ReportSchedule::query()->create([
        'report' => 'supplier-age-analysis', 'frequency' => 'weekly', 'day' => now('Africa/Johannesburg')->dayOfWeekIso, 'format' => 'xlsx',
        'recipients' => [$this->finance->id, $this->siteManager->id], 'created_by' => $this->director->id,
    ]));

    expect(app(ScheduledReportSender::class)->sendDue())->toBe(1)
        ->and(app(ScheduledReportSender::class)->sendDue())->toBe(0);

    Mail::assertSent(ScheduledReport::class, fn (ScheduledReport $m) => $m->hasTo($this->finance->email) && str_ends_with($m->fileName, '.xlsx'));
    Mail::assertNotSent(ScheduledReport::class, fn (ScheduledReport $m) => $m->hasTo($this->siteManager->email));
});
