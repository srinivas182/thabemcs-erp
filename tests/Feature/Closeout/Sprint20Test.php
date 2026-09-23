<?php

declare(strict_types=1);

use App\Domains\Closeout\Models\CloseoutItem;
use App\Domains\Closeout\Models\Distribution;
use App\Domains\Closeout\Services\DistributionService;
use App\Domains\Funding\Models\FundingMovement;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->director = userWithRole($this->company, Role::Director);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['code' => 'BH-01', 'name' => 'Ballito Heights']));
});

/** An investor who has paid money in, on the given terms. */
function investorIn($test, string $name, float $amount, string $on, ?float $preferred = null, ?float $profitShare = null, ?Project $project = null): FundingSource
{
    return inCompany($test->company, function () use ($test, $name, $amount, $on, $preferred, $profitShare, $project) {
        $investor = Investor::query()->create(['name' => $name, 'entity_type' => 'individual']);
        $source = FundingSource::query()->create([
            'project_id' => ($project ?? $test->project)->id, 'investor_id' => $investor->id, 'type' => 'equity', 'name' => "{$name} equity",
            'committed_amount' => $amount, 'status' => 'active', 'preferred_return_percent' => $preferred, 'profit_share_percent' => $profitShare,
        ]);
        FundingMovement::query()->create(['funding_source_id' => $source->id, 'direction' => 'in', 'amount' => $amount, 'occurred_on' => $on, 'recorded_by' => $test->director->id]);

        return $source;
    });
}

it('builds the close-out checklist and refuses to close while required items are outstanding', function (): void {
    $this->actingAs($this->director)->get("/projects/{$this->project->ulid}/closeout")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/closeout')
            ->where('readiness.done', 0)->has('items', 24));

    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/closeout/close")
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'outstanding'));

    // Optional items (gas certificate, fire sign-off, body corporate) do not block closing.
    inCompany($this->company, function (): void {
        CloseoutItem::query()->where('project_id', $this->project->id)->where('required', true)
            ->update(['completed_on' => now()->toDateString(), 'completed_by' => $this->director->id]);
    });

    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/closeout/close")->assertSessionHas('success');
    expect($this->project->fresh()->status->value)->toBe('completed');
});

it('pays capital back first, then the preferred return, then shares the profit', function (): void {
    $paidIn = now()->subYear()->toDateString();
    investorIn($this, 'Ma Queen', 2_000_000, $paidIn, 10);
    investorIn($this, 'Coastal Fund', 1_000_000, $paidIn, 10);

    // A year later, R1 500 000 is available: not enough to return all the capital.
    $part = inCompany($this->company, fn () => app(DistributionService::class)->preview($this->project, 1_500_000, now()));
    expect($part['capital'])->toBe(1_500_000.0)->and($part['preferred'])->toBe(0.0)->and($part['profit'])->toBe(0.0)
        // Shared in proportion: two thirds and one third.
        ->and($part['lines'][0]['capital'])->toBe(1_000_000.0)->and($part['lines'][1]['capital'])->toBe(500_000.0);

    // With R4 000 000 there is enough for capital (R3m), the 10% preferred return (R300 000) and profit.
    $full = inCompany($this->company, fn () => app(DistributionService::class)->preview($this->project, 4_000_000, now()));
    expect($full['capital'])->toBe(3_000_000.0)->and(round($full['preferred'], -3))->toBe(300_000.0)
        ->and($full['unallocated'])->toBe(0.0)
        ->and(round($full['capital'] + $full['preferred'] + $full['profit'], 2))->toBe(4_000_000.0);
});

it('records a distribution against each investor once it is approved and paid', function (): void {
    $source = investorIn($this, 'Ma Queen', 1_000_000, now()->subMonths(11)->toDateString());

    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/distributions", [
        'amount' => 400_000, 'declared_on' => now()->toDateString(), 'notes' => 'First return of capital',
    ])->assertSessionHas('success');

    $distribution = inCompany($this->company, fn () => Distribution::query()->with('lines')->sole());
    expect($distribution->reference())->toBe('D-0001')->and($distribution->status)->toBe('draft')
        ->and((float) $distribution->lines[0]->capital)->toBe(400_000.0);

    // Paying before approval is refused.
    $this->actingAs($this->director)->post("/distributions/{$distribution->ulid}/pay", ['paid_on' => now()->toDateString()])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'approved'));

    $this->actingAs($this->director)->post("/distributions/{$distribution->ulid}/approve")->assertSessionHas('success');
    $this->actingAs($this->director)->post("/distributions/{$distribution->ulid}/pay", ['paid_on' => now()->toDateString()])->assertSessionHas('success');

    expect($distribution->fresh()->status)->toBe('paid')
        ->and(inCompany($this->company, fn () => (float) FundingMovement::query()->where('funding_source_id', $source->id)->where('direction', 'out')->sum('amount')))->toBe(400_000.0);

    // The next distribution knows R400 000 of capital is already back.
    $positions = inCompany($this->company, fn () => app(DistributionService::class)->positions($this->project));
    expect($positions[0]['capitalOutstanding'])->toBe(600_000.0)->and($positions[0]['paidToDate'])->toBe(400_000.0);

    $this->actingAs($this->director)->get('/reports/investor-returns')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.rows.0.investor', 'Ma Queen'));
});

it('moves a reinvestment into another project\'s funding', function (): void {
    investorIn($this, 'Ma Queen', 500_000, now()->subYear()->toDateString());
    $next = inCompany($this->company, fn () => Project::factory()->create(['code' => 'BH-02']));
    $target = investorIn($this, 'Ma Queen Two', 100_000, now()->subMonth()->toDateString(), null, null, $next);
    $investor = inCompany($this->company, fn () => Investor::query()->where('name', 'Ma Queen')->firstOrFail());

    $this->actingAs($this->director)->post("/projects/{$this->project->ulid}/reinvestments", [
        'investor' => $investor->ulid, 'target' => $target->ulid, 'amount' => 250_000, 'occurred_on' => now()->toDateString(),
    ])->assertSessionHas('success');

    expect(inCompany($this->company, fn () => (float) FundingMovement::query()->where('funding_source_id', $target->id)->where('direction', 'in')->sum('amount')))
        ->toBe(350_000.0);
});

it('shows the final account and keeps close-out away from site staff', function (): void {
    $this->actingAs($this->director)->get("/projects/{$this->project->ulid}/closeout")
        ->assertInertia(fn (Assert $page) => $page->has('finalAccount.final')->where('finalAccount.distributed', fn ($v): bool => (float) $v === 0.0));

    $this->actingAs($this->siteManager)->get("/projects/{$this->project->ulid}/closeout")->assertForbidden();
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/closeout/close")->assertForbidden();
});
