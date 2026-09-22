<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Suppliers\Exceptions\SupplierNotCompliantException;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Domains\Suppliers\Services\ComplianceAlerts;
use App\Domains\Suppliers\Services\ComplianceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->buyer = userWithRole($this->company, Role::Procurement);
    $this->supplier = inCompany($this->company, fn () => Supplier::query()->create(['name' => 'Ndlovu Builders', 'type' => 'contractor', 'cidb_grade' => 5, 'cidb_class' => 'GB']));
});

function giveAllDocuments(Supplier $supplier, ?string $expires = null): void
{
    foreach (array_keys(config('supplier_compliance.required.contractor')) as $type) {
        SupplierDocument::query()->create([
            'supplier_id' => $supplier->id, 'type' => $type,
            'expires_on' => config("supplier_compliance.documents.{$type}.expires") ? ($expires ?? now()->addYear()->toDateString()) : null,
        ]);
    }
}

it('blocks a contractor with missing documents and clears them once all are held', function (): void {
    $service = app(ComplianceService::class);

    inCompany($this->company, function () use ($service): void {
        expect($service->isCompliant($this->supplier))->toBeFalse()
            ->and($service->blockers($this->supplier))->toContain('SARS tax compliance status (PIN) is missing');

        giveAllDocuments($this->supplier);
        expect($service->isCompliant($this->supplier->fresh()))->toBeTrue();
    });
});

it('treats expired documents as blocking and the latest upload as current', function (): void {
    $service = app(ComplianceService::class);

    inCompany($this->company, function () use ($service): void {
        giveAllDocuments($this->supplier, now()->subDay()->toDateString());
        expect(fn () => $service->ensureCanTransact($this->supplier))->toThrow(SupplierNotCompliantException::class);

        giveAllDocuments($this->supplier, now()->addMonths(6)->toDateString());
        expect($service->isCompliant($this->supplier->fresh()))->toBeTrue();
    });
});

it('checks the contract value against the CIDB grade', function (): void {
    $service = app(ComplianceService::class);

    inCompany($this->company, function () use ($service): void {
        giveAllDocuments($this->supplier);

        expect($service->blockers($this->supplier, 8_000_000))->toBe([])
            ->and($service->blockers($this->supplier, 25_000_000))->toContain('CIDB grade 5 allows contracts up to R10 000 000');
    });
});

it('records a compliance document with an uploaded copy stored privately', function (): void {
    Storage::fake('documents');

    $this->actingAs($this->buyer)->post("/suppliers/{$this->supplier->ulid}/documents", [
        'type' => 'tax_compliance', 'reference' => 'PIN 12345', 'expires_on' => now()->addYear()->toDateString(),
        'file' => UploadedFile::fake()->create('tcs.pdf', 120, 'application/pdf'),
    ])->assertSessionHas('success');

    $doc = inCompany($this->company, fn () => SupplierDocument::query()->with('document.latestVersion')->firstOrFail());
    expect($doc->document)->not->toBeNull();
    Storage::disk('documents')->assertExists($doc->document->latestVersion->path);

    $this->actingAs($this->buyer)->get("/suppliers/{$this->supplier->ulid}")
        ->assertInertia(fn (Assert $page) => $page->component('suppliers/show')->where('compliance.1.state', 'valid'));
});

it('sends expiry warnings to procurement at 30, 14 and 7 days', function (): void {
    Notification::fake();

    inCompany($this->company, fn () => SupplierDocument::query()->create([
        'supplier_id' => $this->supplier->id, 'type' => 'coida', 'expires_on' => now()->addDays(14)->toDateString(),
    ]));
    inCompany($this->company, fn () => SupplierDocument::query()->create([
        'supplier_id' => $this->supplier->id, 'type' => 'bbbee', 'expires_on' => now()->addDays(20)->toDateString(),
    ]));

    expect(app(ComplianceAlerts::class)->send())->toBe(1);
    Notification::assertSentTo($this->buyer, SystemMessage::class, fn (SystemMessage $m) => str_contains($m->title, 'expires in 14 days'));
});

it('keeps suppliers away from roles that do not manage them', function (): void {
    $pm = userWithRole($this->company, Role::ProjectManager);

    $this->actingAs($pm)->get('/suppliers')->assertOk();
    $this->actingAs($pm)->post('/suppliers', ['name' => 'X', 'type' => 'supplier'])->assertForbidden();
});

it('allows a grade 9 contractor to take a contract of any value', function (): void {
    $service = app(ComplianceService::class);
    $this->supplier->update(['cidb_grade' => 9]);

    expect($service->cidbLimit(9))->toBeNull()
        ->and($service->cidbProblem($this->supplier->fresh(), 900_000_000))->toBeNull();
});
