<?php

declare(strict_types=1);

use App\Domains\Integrations\Models\Integration;
use App\Domains\Integrations\Services\SageZaConnector;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\LeaseInvoice;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Sales\Models\SaleUnit;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->director = userWithRole($this->company, Role::Director);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
});

it('sets security headers and a request id on every response', function (): void {
    $response = $this->actingAs($this->siteManager)->get('/dashboard/portfolio');

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('X-Request-Id'))->not->toBeNull();

    // A request id sent by the load balancer is kept, so one request can be traced end to end.
    $this->actingAs($this->siteManager)->get('/dashboard/portfolio', ['X-Request-Id' => 'trace-me-123'])
        ->assertHeader('X-Request-Id', 'trace-me-123');
});

it('makes people who approve work and move money set up two-factor authentication', function (): void {
    // A Director who has not set it up yet is sent to their profile, whatever they try to open.
    $this->director->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->director->refresh();
    $this->actingAs($this->director)->get('/projects')->assertRedirect('/settings/profile');
    $this->actingAs($this->director)->get('/settings/profile')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('twoFactor.required', true)->where('twoFactor.confirmed', false));

    // A Site Manager is not affected.
    $this->actingAs($this->siteManager)->get('/projects')->assertOk();

    // Once it is set up, the Director works normally again.
    $this->director->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->director->fresh())->get('/projects')->assertOk();
});

it('lets someone sign out of every other device, with their password', function (): void {
    $this->actingAs($this->siteManager)->post('/settings/profile/sign-out-others', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->siteManager)->post('/settings/profile/sign-out-others', ['password' => 'password'])
        ->assertSessionHas('success');
});

it('offers help for the page being shown', function (): void {
    $this->actingAs($this->siteManager)->get('/projects')
        ->assertInertia(fn (Assert $page) => $page->where('help.title', 'Projects')->has('help.steps'));

    $this->actingAs($this->siteManager)->get('/inbox')
        ->assertInertia(fn (Assert $page) => $page->where('help', null));
});

it('sends rental invoices to Sage once, against the tenant customer account', function (): void {
    Http::fake(['*/CustomerInvoice/Save*' => Http::response(['ID' => 5150]), '*' => Http::response([])]);
    $finance = userWithRole($this->company, Role::Finance);
    $letting = userWithRole($this->company, Role::SalesAndLeasing);

    inCompany($this->company, function () use ($letting): void {
        $project = Project::factory()->create(['code' => 'BH-01']);
        $unit = SaleUnit::query()->create(['project_id' => $project->id, 'reference' => 'Unit 2', 'type' => 'sectional_unit', 'list_price' => 0, 'tenure' => 'rental']);
        $tenant = Tenant::query()->create(['name' => 'Sipho Ndlovu', 'entity_type' => 'individual', 'status' => 'current', 'accounting_ref' => '7788']);
        $lease = Lease::query()->create([
            'number' => 1, 'sale_unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'type' => 'residential', 'starts_on' => now()->subMonths(2)->toDateString(),
            'rent_amount' => 11_500, 'payment_day' => 1, 'status' => 'active', 'created_by' => $letting->id,
        ]);
        LeaseInvoice::query()->create([
            'number' => 1, 'lease_id' => $lease->id, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString(),
            'due_on' => now()->startOfMonth()->toDateString(), 'lines' => [['description' => 'Monthly rent', 'amount' => 11_500.0, 'vat' => 0.0]],
            'subtotal' => 11_500, 'vat' => 0, 'total' => 11_500, 'status' => 'issued',
        ]);
    });

    $this->actingAs($finance)->put('/settings/integrations/sage_za', [
        'enabled' => true, 'credentials' => ['api_key' => 'KEY', 'username' => 'finance@example.co.za', 'password' => 'secret'],
        'settings' => ['company_id' => '555', 'default_account_id' => '1000', 'rental_income_account_id' => '3100', 'tax_type_none' => '2'],
    ]);

    $integration = inCompany($this->company, fn () => Integration::query()->where('provider', 'sage_za')->sole());
    $first = inCompany($this->company, fn () => app(SageZaConnector::class)->pushRentalInvoices($integration));
    expect($first['sent'])->toBe(1)->and($first['failed'])->toBe(0);

    Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'CustomerInvoice/Save')
        && $r['CustomerId'] === 7788 && $r['Reference'] === 'RI-00001' && $r['Lines'][0]['SelectionId'] === 3100);

    // Running it again sends nothing new.
    $second = inCompany($this->company, fn () => app(SageZaConnector::class)->pushRentalInvoices($integration));
    expect($second['sent'])->toBe(0);
});
