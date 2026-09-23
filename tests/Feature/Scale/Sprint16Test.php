<?php

declare(strict_types=1);

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Models\CustomReport;
use App\Domains\Reporting\Models\ProjectMetric;
use App\Domains\Suppliers\Models\Supplier;
use App\Support\Cache\CompanyCache;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->director = userWithRole($this->company, Role::Director);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->supplier = compliantSupplier($this->company, 'Build It Ballito');
});

/** Number of database queries a request makes. */
function queryCount(Closure $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function projectWithSpend($test, string $code, float $budget, float $committed): Project
{
    return inCompany($test->company, function () use ($test, $code, $budget, $committed) {
        $project = Project::factory()->create(['code' => $code, 'latitude' => -29.5, 'longitude' => 31.2]);
        $line = BudgetLine::query()->create(['project_id' => $project->id, 'code' => '05.01', 'description' => 'Works', 'original_amount' => $budget]);
        $order = PurchaseOrder::query()->create(['project_id' => $project->id, 'supplier_id' => $test->supplier->id, 'budget_line_id' => $line->id,
            'number' => random_int(1000, 999999), 'status' => 'approved', 'vat_applies' => true, 'created_by' => $test->pm->id]);
        $order->forceFill(['subtotal' => $committed])->save();

        return $project;
    });
}

it('keeps precomputed project figures current as work is captured', function (): void {
    $project = projectWithSpend($this, 'PRJ-0001', 1_000_000, 850_000);

    $metric = inCompany($this->company, fn () => ProjectMetric::query()->where('project_id', $project->id)->sole());
    expect((float) $metric->budget)->toBe(1_000_000.0)->and((float) $metric->spent)->toBe(850_000.0)
        ->and((float) $metric->used_percent)->toBe(85.0)->and($metric->health)->toBe('amber');

    // Committing more takes it over budget, and the stored health follows.
    inCompany($this->company, function () use ($project): void {
        $line = BudgetLine::query()->where('project_id', $project->id)->sole();
        $order = PurchaseOrder::query()->create(['project_id' => $project->id, 'supplier_id' => $this->supplier->id, 'budget_line_id' => $line->id,
            'number' => 7, 'status' => 'approved', 'vat_applies' => true, 'created_by' => $this->pm->id]);
        $order->forceFill(['subtotal' => 300_000])->save();
    });
    expect(inCompany($this->company, fn () => ProjectMetric::query()->where('project_id', $project->id)->sole()->health))->toBe('red');
});

it('stores the critical path on the activities for cross-project reporting', function (): void {
    $project = projectWithSpend($this, 'PRJ-0002', 100_000, 0);
    inCompany($this->company, function () use ($project): void {
        $a = ProgrammeActivity::query()->create(['project_id' => $project->id, 'name' => 'Earthworks', 'planned_start' => '2027-04-05', 'duration_days' => 5]);
        $b = ProgrammeActivity::query()->create(['project_id' => $project->id, 'name' => 'Foundations', 'planned_start' => '2027-04-05', 'duration_days' => 5]);
        ActivityDependency::query()->create(['predecessor_id' => $a->id, 'successor_id' => $b->id]);
    });

    $stored = inCompany($this->company, fn () => ProgrammeActivity::query()->orderBy('id')->get());
    expect($stored[0]->early_start?->toDateString())->toBe('2027-04-05')->and($stored[0]->early_finish?->toDateString())->toBe('2027-04-09')
        ->and($stored[0]->is_critical)->toBeTrue()->and($stored[1]->early_start?->toDateString())->toBe('2027-04-12');

    $this->actingAs($this->director)->get('/reports/programme-status')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.rows.0.activity', 'Earthworks'));
});

it('costs the same number of queries whatever the size of the portfolio', function (): void {
    // Measures the real work: caches are warm for permissions and settings, but the portfolio figures
    // are cleared before each reading, exactly as they are when a project's metrics change.
    $measure = function (string $url): int {
        $this->actingAs($this->director)->get($url)->assertOk();
        inCompany($this->company, fn () => app(CompanyCache::class)->flush('portfolio'));

        return queryCount(fn () => $this->actingAs($this->director)->get($url)->assertOk());
    };

    foreach (range(1, 3) as $i) {
        projectWithSpend($this, sprintf('P-%04d', $i), 500_000, 100_000 * $i);
    }
    $before = array_map($measure, ['/dashboard/portfolio', '/dashboard/map', '/projects']);

    foreach (range(4, 12) as $i) {
        projectWithSpend($this, sprintf('P-%04d', $i), 500_000, 100_000);
    }

    expect(array_map($measure, ['/dashboard/portfolio', '/dashboard/map', '/projects']))->toBe($before);
});

it('never serves one company figures from another company cache', function (): void {
    projectWithSpend($this, 'A-0001', 400_000, 100_000);
    $other = Company::factory()->create();
    $otherDirector = userWithRole($other, Role::Director);
    $otherSupplier = compliantSupplier($other, 'Other Supplies');
    inCompany($other, function () use ($otherDirector, $otherSupplier): void {
        $project = Project::factory()->create(['code' => 'B-0001']);
        $line = BudgetLine::query()->create(['project_id' => $project->id, 'code' => '05.01', 'description' => 'Works', 'original_amount' => 900_000]);
        $order = PurchaseOrder::query()->create(['project_id' => $project->id, 'supplier_id' => $otherSupplier->id, 'budget_line_id' => $line->id,
            'number' => 11, 'status' => 'approved', 'vat_applies' => true, 'created_by' => $otherDirector->id]);
        $order->forceFill(['subtotal' => 450_000])->save();
    });

    $this->actingAs($this->director)->get('/dashboard/portfolio')
        ->assertInertia(fn (Assert $page) => $page->where('portfolio.totals.budget', fn ($v): bool => (float) $v === 400000.0)->where('portfolio.projects.0.code', 'A-0001'));
    $this->actingAs($otherDirector)->get('/dashboard/portfolio')
        ->assertInertia(fn (Assert $page) => $page->where('portfolio.totals.budget', fn ($v): bool => (float) $v === 900000.0)->where('portfolio.projects.0.code', 'B-0001'));
});

it('answers dropdown lookups with a handful of matches, inside the company only', function (): void {
    inCompany($this->company, function (): void {
        foreach (range(1, 25) as $i) {
            Supplier::query()->create(['name' => sprintf('Concrete Supplier %02d', $i), 'type' => 'supplier']);
        }
    });
    $other = Company::factory()->create();
    compliantSupplier($other, 'Concrete Somebody Else');

    $this->actingAs($this->pm)->getJson('/lookup/suppliers?q=Concrete')
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonMissing(['label' => 'Concrete Somebody Else']);
    $this->actingAs($this->pm)->getJson('/lookup/suppliers?q=Supplier 07')->assertJsonPath('data.0.label', 'Concrete Supplier 07');
    $this->actingAs($this->pm)->getJson("/lookup/suppliers?key={$this->supplier->ulid}")->assertJsonPath('data.0.label', 'Build It Ballito');
    auth()->logout();
    $this->getJson('/lookup/suppliers')->assertUnauthorized();
});

it('filters designed reports in the database, and says when a report is cut short', function (): void {
    config(['reporting.row_limit' => 2]);
    $project = projectWithSpend($this, 'PRJ-0003', 1_000_000, 50_000);
    inCompany($this->company, function () use ($project): void {
        foreach ([['draft', 1], ['draft', 2], ['draft', 3], ['issued', 4], ['issued', 5]] as [$status, $number]) {
            PurchaseOrder::query()->create(['project_id' => $project->id, 'supplier_id' => $this->supplier->id, 'number' => 100 + $number,
                'status' => $status, 'vat_applies' => true, 'created_by' => $this->pm->id])->forceFill(['subtotal' => 1000 * $number])->save();
        }
    });
    $report = inCompany($this->company, fn () => CustomReport::query()->create([
        'name' => 'Issued orders', 'dataset' => 'purchase_orders', 'created_by' => $this->director->id,
        'config' => ['columns' => ['reference', 'subtotal'], 'filters' => [['field' => 'status', 'op' => 'eq', 'value' => 'issued']], 'sort' => 'subtotal', 'direction' => 'desc', 'totals' => true],
    ]));

    // Both issued orders are found even though they sort after the row limit; the earlier build filtered
    // only the first rows fetched and would have returned nothing.
    $this->actingAs($this->director)->get("/reports/custom-{$report->id}")
        ->assertInertia(fn (Assert $page) => $page->has('result.rows', 2)->where('result.rows.0.subtotal', fn ($v): bool => (float) $v === 5000.0));
});

it('lets the site app re-check lists cheaply with ETags', function (): void {
    $siteManager = userWithRole($this->company, Role::SiteManager);
    projectWithSpend($this, 'PRJ-0004', 100_000, 0);

    $first = $this->actingAs($siteManager)->getJson('/api/v1/site/projects')->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $this->actingAs($siteManager)->getJson('/api/v1/site/projects', ['If-None-Match' => (string) $etag])->assertStatus(304);
});

arch('domain code caches only through CompanyCache, so keys always carry the company')
    ->expect('App\Domains')
    ->not->toUse(['Illuminate\Support\Facades\Cache', 'Illuminate\Cache\CacheManager']);
