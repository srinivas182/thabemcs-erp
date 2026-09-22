<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\Exceptions\MissingCompanyContextException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->alpha = Company::factory()->create(['name' => 'Alpha Developments']);
    $this->beta = Company::factory()->create(['name' => 'Beta Construction']);

    inCompany($this->alpha, fn () => Project::factory()->count(3)->create());
    inCompany($this->beta, fn () => Project::factory()->count(2)->create());
});

it('only returns records belonging to the current company', function (): void {
    expect(inCompany($this->alpha, fn () => Project::query()->count()))->toBe(3)
        ->and(inCompany($this->beta, fn () => Project::query()->count()))->toBe(2);
});

it('fails closed when there is no company context', function (): void {
    expect(Project::query()->count())->toBe(0);
});

it('allows cross-company reads only with explicit platform access', function (): void {
    $total = app(CurrentCompany::class)->runAsPlatform(fn () => Project::query()->count());

    expect($total)->toBe(5)
        ->and(Project::query()->count())->toBe(0);
});

it('stamps the current company on new records automatically', function (): void {
    $project = inCompany($this->beta, fn () => Project::factory()->create());

    expect($project->company_id)->toBe($this->beta->id);
});

it('refuses to create a company-owned record without a company context', function (): void {
    Project::factory()->create();
})->throws(MissingCompanyContextException::class);

it('prevents moving a record to another company', function (): void {
    inCompany($this->alpha, function (): void {
        $project = Project::query()->firstOrFail();
        $project->company_id = $this->beta->id;
        $project->save();
    });
})->throws(LogicException::class);

it('shows each user only their own company on My Day', function (): void {
    $user = User::factory()->forCompany($this->beta)->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('my-day')
            ->where('pipeline.0.key', 'plan')
            ->where('pipeline.0.count', 2)
            ->where('company.name', 'Beta Construction'));
});
