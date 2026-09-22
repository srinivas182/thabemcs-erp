<?php

declare(strict_types=1);

use App\Domains\Land\Enums\LandStatus;
use App\Domains\Land\Models\LandCheck;
use App\Domains\Land\Models\LandParcel;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->dm = userWithRole($this->company, Role::DevelopmentManager);
    $this->actingAs($this->dm)->post('/land', ['name' => 'Ballito ridge site', 'property_description' => 'Erf 1234 Ballito', 'province' => 'KZN', 'asking_price' => 9500000]);
    $this->parcel = inCompany($this->company, fn () => LandParcel::query()->firstOrFail());
});

it('creates land with the full SA due-diligence checklist', function (): void {
    expect(inCompany($this->company, fn () => LandCheck::query()->where('land_parcel_id', $this->parcel->id)->count()))
        ->toBe(count(config('land_checks')));
});

it('requires notes when a check shows an issue', function (): void {
    $check = inCompany($this->company, fn () => LandCheck::query()->where('key', 'land_claims')->firstOrFail());

    $this->actingAs($this->dm)->patch("/land/{$this->parcel->ulid}/checks/{$check->id}", ['result' => 'issue'])
        ->assertSessionHasErrors('notes');

    $this->actingAs($this->dm)->patch("/land/{$this->parcel->ulid}/checks/{$check->id}", ['result' => 'issue', 'notes' => 'Claim lodged with the Land Claims Commission'])
        ->assertSessionHasNoErrors();

    expect($check->fresh()->checked_by)->toBe($this->dm->id);
});

it('blocks accepting an offer until due diligence is complete', function (): void {
    $this->actingAs($this->dm)->patch("/land/{$this->parcel->ulid}/status", ['status' => 'offer_accepted'])
        ->assertSessionHasErrors('status');

    inCompany($this->company, fn () => LandCheck::query()->update(['result' => 'clear']));

    $this->actingAs($this->dm)->patch("/land/{$this->parcel->ulid}/status", ['status' => 'offer_accepted'])->assertSessionHas('success');

    expect($this->parcel->fresh()->status)->toBe(LandStatus::OfferAccepted)
        ->and($this->parcel->fresh()->acceptance_date)->not->toBeNull();
});

it('links land to a project in the same company only', function (): void {
    $mine = inCompany($this->company, fn () => Project::factory()->create());
    $theirs = inCompany(Company::factory()->create(), fn () => Project::factory()->create());

    $this->actingAs($this->dm)->put("/land/{$this->parcel->ulid}", ['name' => 'Ballito ridge site', 'project' => $theirs->ulid])->assertSessionHasErrors('project');
    $this->actingAs($this->dm)->put("/land/{$this->parcel->ulid}", ['name' => 'Ballito ridge site', 'project' => $mine->ulid])->assertSessionHasNoErrors();

    expect($this->parcel->fresh()->project_id)->toBe($mine->id);
});

it('shows the pipeline with outstanding checks and hides other companies\' land', function (): void {
    $this->actingAs($this->dm)->get('/land')->assertInertia(fn (Assert $page) => $page->component('land/index')
        ->has('parcels', 1)->where('parcels.0.openChecks', collect(config('land_checks'))->where('required', true)->count()));

    $other = userWithRole(Company::factory()->create(), Role::DevelopmentManager);
    $this->actingAs($other)->get("/land/{$this->parcel->ulid}")->assertNotFound();
});
