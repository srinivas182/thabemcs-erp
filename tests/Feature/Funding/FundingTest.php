<?php

declare(strict_types=1);

use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());
});

it('keeps investor ID numbers encrypted and shows only the last four characters', function (): void {
    $this->actingAs($this->finance)->post('/investors', [
        'name' => 'Nomvula Khumalo', 'entity_type' => 'individual', 'registration_number' => '8001015009087', 'email' => 'nomvula@example.test',
    ])->assertSessionHas('success');

    $raw = DB::table('investors')->value('registration_number');
    expect($raw)->not->toContain('8001015009087');

    $this->actingAs($this->finance)->get('/investors')
        ->assertInertia(fn (Assert $page) => $page->where('investors.0.registration', '•••••••••9087')->where('investors.0.ficaVerifiedAt', null));
});

it('records funding sources, money received and the gap against the baseline requirement', function (): void {
    $investor = inCompany($this->company, fn () => Investor::query()->create(['name' => 'Ubuntu Capital', 'entity_type' => 'fund']));

    inCompany($this->company, function (): void {
        $service = app(FeasibilityService::class);
        $f = $service->create($this->project, 'Base case', 12, null);
        $service->saveLines($f, [
            ['category' => 'land', 'description' => 'Land', 'basis' => 'amount', 'amount' => 5000000, 'rate' => null, 'start_month' => 1, 'end_month' => 1],
            ['category' => 'revenue', 'description' => 'Sales', 'basis' => 'amount', 'amount' => 8000000, 'rate' => null, 'start_month' => 12, 'end_month' => 12],
        ], 12, null);
        $service->approve($f->fresh(), $this->finance);
    });

    $this->actingAs($this->finance)->post("/projects/{$this->project->ulid}/funding-sources", [
        'type' => 'investor', 'name' => 'Ubuntu Capital equity', 'investor' => $investor->ulid, 'committed_amount' => 3000000, 'status' => 'committed',
    ])->assertSessionHas('success');

    $source = inCompany($this->company, fn () => FundingSource::query()->firstOrFail());
    $this->actingAs($this->finance)->post("/funding-sources/{$source->ulid}/movements", [
        'direction' => 'in', 'amount' => 1000000, 'occurred_on' => now()->toDateString(), 'reference' => 'EFT 001',
    ])->assertSessionHas('success');

    $this->actingAs($this->finance)->get("/projects/{$this->project->ulid}/funding")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/funding')
            ->where('summary.requirement', 5000000)
            ->where('summary.committed', 3000000)
            ->where('summary.received', 1000000)
            ->where('summary.gap', 2000000));
});

it('requires an interest rate for loans and stores only the last four account digits', function (): void {
    $this->actingAs($this->finance)->post("/projects/{$this->project->ulid}/funding-sources", [
        'type' => 'debt', 'name' => 'Development loan', 'committed_amount' => 20000000, 'status' => 'proposed',
    ])->assertSessionHasErrors('interest_rate');

    $this->actingAs($this->finance)->put("/projects/{$this->project->ulid}/bank-account", [
        'bank' => 'Standard Bank', 'account_name' => 'Ballito Heights Project', 'account_last4' => '12345678',
    ])->assertSessionHasErrors('account_last4');
});

it('keeps funding away from project managers and other companies', function (): void {
    $pm = userWithRole($this->company, Role::ProjectManager);
    $this->actingAs($pm)->get('/investors')->assertForbidden();

    $other = inCompany(Company::factory()->create(), fn () => Project::factory()->create());
    $this->actingAs($this->finance)->get("/projects/{$other->ulid}/funding")->assertNotFound();
});
