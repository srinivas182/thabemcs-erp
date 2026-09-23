<?php

declare(strict_types=1);

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Jobs\DeliverWebhook;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\ImportRun;
use App\Domains\Platform\Models\NotificationPreference;
use App\Domains\Platform\Models\ReportPreset;
use App\Domains\Platform\Models\Webhook;
use App\Domains\Platform\Models\WebhookDelivery;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Platform\Services\WebhookDispatcher;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['name' => 'Thabekhulu Developments', 'registration_number' => '2019/123456/07']);
    $this->admin = userWithRole($this->company, Role::CompanyAdmin);
    $this->director = userWithRole($this->company, Role::Director);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'BH-01']));
});

it('checks an import before it writes anything, and rolls the whole file back if a row is wrong', function (): void {
    $good = "name,type,email\nBallito Roofing,supplier,roofs@example.co.za\nZulu Civils,contractor,info@example.co.za\n";
    $bad = "name,type,email\nGood Supplier,supplier,ok@example.co.za\n,supplier,missing@example.co.za\n";

    // Checking only: nothing is written.
    $this->actingAs($this->admin)->post('/settings/import', [
        'type' => 'suppliers', 'file' => UploadedFile::fake()->createWithContent('suppliers.csv', $good),
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, '2 rows checked'));
    expect(inCompany($this->company, fn () => Supplier::query()->count()))->toBe(0);

    $this->actingAs($this->admin)->post('/settings/import', [
        'type' => 'suppliers', 'file' => UploadedFile::fake()->createWithContent('suppliers.csv', $good), 'confirm' => true,
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, '2 rows imported'));
    expect(inCompany($this->company, fn () => Supplier::query()->count()))->toBe(2);

    // One bad row stops the whole file, so the good row in it is not imported either.
    $this->actingAs($this->admin)->post('/settings/import', [
        'type' => 'suppliers', 'file' => UploadedFile::fake()->createWithContent('more.csv', $bad), 'confirm' => true,
    ])->assertSessionHas('error');
    expect(inCompany($this->company, fn () => Supplier::query()->count()))->toBe(2)
        ->and(inCompany($this->company, fn () => ImportRun::query()->latest('id')->first()->status))->toBe('failed');

    // Names already in the system are refused rather than duplicated.
    $this->actingAs($this->admin)->post('/settings/import', [
        'type' => 'suppliers', 'file' => UploadedFile::fake()->createWithContent('again.csv', $good), 'confirm' => true,
    ])->assertSessionHas('error');
    expect(inCompany($this->company, fn () => Supplier::query()->count()))->toBe(2);

    $this->actingAs($this->admin)->get('/settings/import/suppliers/template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('tells other systems about events with a signed webhook', function (): void {
    Http::fake(['*' => Http::response(['ok' => true])]);
    $this->actingAs($this->finance)->post('/settings/api/webhooks', [
        'name' => 'Accounting bridge', 'url' => 'https://example.co.za/hooks/thabekhulu', 'events' => ['invoice.approved', 'sale.registered'],
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, 'signing secret'));

    $webhook = inCompany($this->company, fn () => Webhook::query()->sole());
    $sent = inCompany($this->company, fn () => app(WebhookDispatcher::class)->send('invoice.approved', ['invoice' => 'INV-1', 'total' => 1150.0]));
    expect($sent)->toBe(1);

    $delivery = inCompany($this->company, fn () => WebhookDelivery::query()->sole());
    inCompany($this->company, fn () => (new DeliverWebhook((int) $this->company->getKey(), $delivery->id))->handle(app(CurrentCompany::class)));

    Http::assertSent(function (HttpRequest $request) use ($webhook): bool {
        $expected = hash_hmac('sha256', $request->body(), (string) $webhook->secret);

        return $request->url() === 'https://example.co.za/hooks/thabekhulu'
            && $request->hasHeader('X-Thabekhulu-Signature', $expected)
            && $request->hasHeader('X-Thabekhulu-Event', 'invoice.approved');
    });
    expect($delivery->fresh()->delivered_at)->not->toBeNull();

    // Events nobody subscribed to are not sent.
    expect(inCompany($this->company, fn () => app(WebhookDispatcher::class)->send('lease.started', [])))->toBe(0);
});

it('fires a webhook when a supplier invoice is approved', function (): void {
    Http::fake(['*' => Http::response([], 200)]);
    $supplier = compliantSupplier($this->company, 'Zulu Civils', 'contractor');
    inCompany($this->company, function (): void {
        Webhook::query()->create([
            'name' => 'Bridge', 'url' => 'https://example.co.za/hook', 'events' => ['invoice.approved'],
            'secret' => 'shhh', 'active' => true, 'created_by' => $this->finance->id,
        ]);
    });
    $invoice = inCompany($this->company, function () use ($supplier) {
        $invoice = SupplierInvoice::query()->create([
            'project_id' => $this->project->id, 'supplier_id' => $supplier->id, 'invoice_number' => 'Z-9', 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 1000, 'vat' => 150, 'total' => 1150, 'captured_by' => $this->finance->id,
        ]);
        $invoice->forceFill(['status' => 'matched'])->save();

        return $invoice;
    });

    inCompany($this->company, fn () => app(InvoiceService::class)->approve($invoice, $this->finance, null));
    expect(inCompany($this->company, fn () => WebhookDelivery::query()->where('event', 'invoice.approved')->count()))->toBe(1);
});

it('saves a report layout and shows the letterhead', function (): void {
    $this->actingAs($this->director)->get('/reports/supplier-compliance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('letterhead.company', 'Thabekhulu Developments')->where('letterhead.registration', '2019/123456/07'));

    $this->actingAs($this->director)->post('/reports/supplier-compliance/presets', [
        'name' => 'For the board', 'columns' => ['supplier', 'document', 'state'], 'is_default' => true,
    ])->assertSessionHas('success');

    $preset = inCompany($this->company, fn () => ReportPreset::query()->sole());
    expect($preset->is_default)->toBeTrue();

    // The default layout keeps only the chosen columns, in order.
    $this->actingAs($this->director)->get('/reports/supplier-compliance')
        ->assertInertia(fn (Assert $page) => $page->has('result.columns', 3)->where('result.columns.0.key', 'supplier')->where('presetId', $preset->id));

    // Asking for all columns still works.
    $this->actingAs($this->director)->get('/reports/supplier-compliance?preset=')
        ->assertInertia(fn (Assert $page) => $page->has('result.columns', 3));
});

it('emails people who want to hear immediately, and keeps digest people to their inbox', function (): void {
    Notification::fake();
    $this->actingAs($this->director)->patch('/settings/notifications', ['email_immediately' => true, 'daily_digest' => false])->assertSessionHas('success');
    $this->actingAs($this->finance)->patch('/settings/notifications', ['email_immediately' => true, 'daily_digest' => true])->assertSessionHas('success');

    $message = new SystemMessage('Something happened', 'Details here', null);
    expect($message->via($this->director))->toBe(['database', 'mail'])
        ->and($message->via($this->finance))->toBe(['database'])
        // Someone who has not chosen gets the inbox only.
        ->and($message->via($this->admin))->toBe(['database'])
        ->and(inCompany($this->company, fn () => NotificationPreference::query()->count()))->toBe(2);
});

it('issues read-only API tokens and keeps settings away from other staff', function (): void {
    $this->actingAs($this->finance)->post('/settings/api/tokens', ['name' => "Accountant's dashboard"])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, '|'));

    $siteManager = userWithRole($this->company, Role::SiteManager);
    $this->actingAs($siteManager)->get('/settings/api')->assertForbidden();
    $this->actingAs($siteManager)->get('/settings/import')->assertForbidden();
});
