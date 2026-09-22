<?php

declare(strict_types=1);

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormTemplate;
use App\Domains\Integrations\Models\IntegrationSync;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Models\CustomReport;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->director = userWithRole($this->company, Role::Director);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'PRJ-0001', 'latitude' => -29.5389, 'longitude' => 31.2139]));
    $this->supplier = compliantSupplier($this->company, 'Build It Ballito');
});

function order($test, int $number, string $status, float $subtotal): PurchaseOrder
{
    return inCompany($test->company, function () use ($test, $number, $status, $subtotal) {
        $o = PurchaseOrder::query()->create(['project_id' => $test->project->id, 'supplier_id' => $test->supplier->id, 'number' => $number, 'status' => $status, 'vat_applies' => true, 'created_by' => $test->pm->id]);
        $o->forceFill(['subtotal' => $subtotal, 'total' => $subtotal * 1.15])->save();

        return $o;
    });
}

it('saves a designed report that runs, filters, sorts and totals like a standard report', function (): void {
    order($this, 1, 'issued', 60_000);
    order($this, 2, 'issued', 90_000);
    order($this, 3, 'draft', 10_000);

    $this->actingAs($this->finance)->post('/reports/designer', [
        'name' => 'Issued orders', 'dataset' => 'purchase_orders', 'columns' => ['reference', 'supplier', 'subtotal'],
        'filters' => [['field' => 'status', 'op' => 'eq', 'value' => 'issued']], 'sort' => 'subtotal', 'direction' => 'desc', 'totals' => true,
    ])->assertRedirect();
    $report = inCompany($this->company, fn () => CustomReport::query()->firstOrFail());

    $this->actingAs($this->finance)->get("/reports/custom-{$report->id}")
        ->assertInertia(fn (Assert $page) => $page->component('reports/show')
            ->has('result.rows', 2)->where('result.rows.0.reference', 'PO-0002')->where('result.totals.subtotal', fn ($v) => (float) $v === 150000.0)
            ->where('result.columns.2.label', 'Amount excl. VAT'));

    $this->actingAs($this->finance)->get('/reports')->assertInertia(fn (Assert $page) => $page->where('reports', fn ($r) => collect($r)->pluck('key')->contains("custom-{$report->id}")));
    $this->actingAs($this->finance)->get("/reports/custom-{$report->id}/download/csv")->assertOk();

    // Site managers cannot build reports on financial data.
    $this->actingAs($this->siteManager)->post('/reports/designer', ['name' => 'x', 'dataset' => 'purchase_orders', 'columns' => ['reference']])->assertForbidden();
});

it('adds the variation register, programme status and supplier compliance reports', function (): void {
    foreach (['variation-register', 'programme-status', 'supplier-compliance'] as $key) {
        $this->actingAs($this->director)->get("/reports/{$key}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('reports/show'));
    }
});

it('builds forms, versions them, and takes submissions from the site app with photos and pass/fail', function (): void {
    $this->actingAs($this->pm)->post('/forms', ['name' => 'Pre-pour', 'kind' => 'quality', 'fields' => [
        ['label' => 'Grade', 'type' => 'choice', 'required' => true, 'options' => ['25 MPa']],
    ]])->assertSessionHasErrors('fields');

    $this->actingAs($this->pm)->post('/forms', ['name' => 'Pre-pour', 'kind' => 'quality', 'fields' => [
        ['id' => 'cover01', 'label' => 'Cover at least 50 mm', 'type' => 'passfail', 'required' => true],
        ['id' => 'grade01', 'label' => 'Concrete grade', 'type' => 'choice', 'required' => true, 'options' => ['25 MPa', '30 MPa']],
        ['id' => 'photo01', 'label' => 'Photo of reinforcement', 'type' => 'photo', 'required' => true],
    ]])->assertRedirect('/forms');
    $template = inCompany($this->company, fn () => FormTemplate::query()->firstOrFail());

    $this->actingAs($this->siteManager)->getJson('/api/v1/site/forms')->assertJsonPath('data.0.fields.0.id', 'cover01');

    $clientId = (string) Str::uuid();
    $payload = ['projectId' => $this->project->ulid, 'formId' => $template->ulid, 'clientId' => $clientId, 'answers' => json_encode(['cover01' => 'fail', 'grade01' => '30 MPa'])];
    $this->actingAs($this->siteManager)->post('/api/v1/site/form-submissions', [...$payload, 'clientId' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertStatus(422);
    $this->actingAs($this->siteManager)->post('/api/v1/site/form-submissions', [...$payload, 'photo_photo01' => UploadedFile::fake()->image('rebar.jpg')], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.passed', false);
    $this->actingAs($this->siteManager)->post('/api/v1/site/form-submissions', [...$payload, 'photo_photo01' => UploadedFile::fake()->image('rebar.jpg')], ['Accept' => 'application/json'])->assertOk();

    $submission = inCompany($this->company, fn () => FormSubmission::query()->sole());
    expect($submission->answers['photo01'])->toHaveKey('photo')->and($submission->template_version)->toBe(1);

    // Changing the questions makes version 2; the old submission keeps its own copy.
    $this->actingAs($this->pm)->put("/forms/{$template->ulid}", ['name' => 'Pre-pour', 'kind' => 'quality', 'active' => true, 'fields' => [
        ['id' => 'cover01', 'label' => 'Cover at least 50 mm (checked with spacer)', 'type' => 'passfail', 'required' => true],
    ]])->assertRedirect('/forms');
    expect($template->fresh()->version)->toBe(2)->and(count($submission->fresh()->fields))->toBe(3);
});

it('shows live projects on the command centre coloured by health', function (): void {
    inCompany($this->company, function (): void {
        BudgetLine::query()->create(['project_id' => $this->project->id, 'code' => '05.01', 'description' => 'Works', 'original_amount' => 100_000]);
    });
    order($this, 1, 'approved', 85_000)->update(['budget_line_id' => inCompany($this->company, fn () => BudgetLine::query()->value('id'))]);

    $this->actingAs($this->director)->get('/dashboard/map')->assertInertia(fn (Assert $page) => $page->component('reports/map')
        ->where('projects.0.lat', -29.5389)->where('projects.0.health', 'amber'));
});

it('sends approved invoices to Sage once, with the mapped account and VAT type', function (): void {
    Http::fake(['*/SupplierInvoice/Save*' => Http::response(['ID' => 98765]), '*/Company/Get*' => Http::response(['Results' => [['Name' => 'Thabekhulu (Pty) Ltd']]])]);
    inCompany($this->company, function (): void {
        $line = BudgetLine::query()->create(['project_id' => $this->project->id, 'code' => '05.03', 'description' => 'Concrete', 'original_amount' => 500_000]);
        $this->supplier->update(['accounting_ref' => '4411']);
        $i = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $this->supplier->id, 'budget_line_id' => $line->id, 'invoice_number' => 'BI-77', 'invoice_date' => '2027-02-10', 'due_date' => '2027-03-10', 'subtotal' => 10_000, 'vat' => 1_500, 'total' => 11_500, 'captured_by' => $this->finance->id]);
        $i->forceFill(['status' => 'approved'])->save();
        $other = compliantSupplier($this->company, 'No Sage ID Supplies');
        $j = SupplierInvoice::query()->create(['project_id' => $this->project->id, 'supplier_id' => $other->id, 'invoice_number' => 'N-1', 'invoice_date' => '2027-02-11', 'due_date' => '2027-03-11', 'subtotal' => 100, 'vat' => 15, 'total' => 115, 'captured_by' => $this->finance->id]);
        $j->forceFill(['status' => 'approved'])->save();
    });

    $this->actingAs($this->finance)->put('/settings/integrations/sage_za', [
        'enabled' => true, 'credentials' => ['api_key' => 'KEY-123', 'username' => 'finance@thabekhulu.co.za', 'password' => 'S3cret!'],
        'settings' => ['company_id' => '555', 'default_account_id' => '1000', 'tax_type_vat' => '1', 'tax_type_none' => '2', 'accounts' => ['05.03' => '2200']],
    ])->assertSessionHas('success');
    expect((string) DB::table('integrations')->value('credentials'))->not->toContain('S3cret!');

    $this->actingAs($this->finance)->post('/settings/integrations/sage_za/test')->assertSessionHas('success', fn (string $m) => str_contains($m, 'Thabekhulu (Pty) Ltd'));
    $this->actingAs($this->finance)->post('/settings/integrations/sage_za/sync')->assertSessionHas('success', fn (string $m) => str_contains($m, '1 invoices sent') && str_contains($m, 'No Sage ID Supplies'));
    $this->actingAs($this->finance)->post('/settings/integrations/sage_za/sync');

    Http::assertSentCount(2); // the connection test and one invoice; the second run sends nothing new
    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'SupplierInvoice/Save?apikey=KEY-123&companyid=555')
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('finance@thabekhulu.co.za:S3cret!'))
        && $r['SupplierId'] === 4411 && $r['Reference'] === 'BI-77' && $r['Lines'][0]['SelectionId'] === 2200
        && $r['Lines'][0]['TaxTypeId'] === 1 && $r['Lines'][0]['UnitPriceExclusive'] === 10000.0);
    expect(inCompany($this->company, fn () => IntegrationSync::query()->where('status', 'sent')->value('external_id')))->toBe('98765');
});

it('sends overtime, allowances and leave to SimplePay with the configured payslip items', function (): void {
    Http::fake([
        '*/bulk_input' => Http::response([['id' => '8792', 'success' => 'true', 'message' => 'saved']]),
        '*/leave_days/create_multiple' => Http::response(['message' => 'Leave dates have been created', 'ids' => [1, 2]]),
    ]);
    inCompany($this->company, function (): void {
        $e = Employee::query()->create(['employee_number' => 'E1', 'payroll_ref' => '8792', 'first_name' => 'Themba', 'last_name' => 'Khumalo', 'employment_type' => 'permanent', 'start_date' => '2026-01-05', 'days_per_week' => 5]);
        OvertimeEntry::query()->create(['employee_id' => $e->id, 'worked_on' => '2027-03-03', 'hours' => 2, 'rate_multiplier' => 1.5, 'status' => 'approved', 'recorded_by' => $this->siteManager->id]);
        OvertimeEntry::query()->create(['employee_id' => $e->id, 'worked_on' => '2027-03-07', 'hours' => 3, 'rate_multiplier' => 2.0, 'status' => 'approved', 'recorded_by' => $this->siteManager->id]);
        LeaveRequest::query()->create(['employee_id' => $e->id, 'type' => 'annual', 'from_date' => '2027-03-18', 'to_date' => '2027-03-22', 'days' => 2, 'status' => 'approved']);
    });

    $this->actingAs($this->finance)->put('/settings/integrations/simplepay', [
        'enabled' => true, 'credentials' => ['api_key' => 'sp-key'],
        'settings' => ['client_id' => '36818', 'items' => ['overtime' => 'calc.basic_salary.overtime_hours_input', 'overtime_double' => 'calc.basic_salary.sunday_overtime_hours_input'], 'leave_types' => ['annual' => '5']],
    ]);
    $this->actingAs($this->finance)->post('/settings/integrations/simplepay/sync', ['from' => '2027-03-01', 'to' => '2027-03-31'])->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/clients/36818/bulk_input') && $r->hasHeader('Authorization', 'sp-key')
        && $r['entities'][0]['id'] === '8792' && $r['entities'][0]['payslip_date'] === '2027-03-31'
        && $r['entities'][0]['attributes']['calc.basic_salary.overtime_hours_input'] === '2.00'
        && $r['entities'][0]['attributes']['calc.basic_salary.sunday_overtime_hours_input'] === '3.00');
    // 18 to 22 March 2027: Thursday, Friday, then Monday 22 (a public holiday) is skipped.
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/employees/8792/leave_days/create_multiple')
        && $r['dates'] === [['date' => '2027-03-18', 'type_id' => 5], ['date' => '2027-03-19', 'type_id' => 5]]);
});
