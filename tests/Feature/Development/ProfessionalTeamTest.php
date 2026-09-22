<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Domains\Team\Enums\ClaimStatus;
use App\Domains\Team\Models\FeeClaim;
use App\Domains\Team\Models\ProfessionalAppointment;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->qs = userWithRole($this->company, Role::QuantitySurveyor);
    $this->finance = userWithRole($this->company, Role::Finance);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());

    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/team", [
        'discipline' => 'architect', 'firm_name' => 'Nkosi Architects', 'registration_number' => 'PrArch 12345',
        'fee_basis' => 'lump_sum', 'agreed_fee' => 1000000, 'appointed_on' => now()->toDateString(),
    ]);
    $this->appointment = inCompany($this->company, fn () => ProfessionalAppointment::query()->firstOrFail());
});

it('sets the statutory council from the discipline and records verification', function (): void {
    expect($this->appointment->registration_body)->toBe('SACAP')
        ->and($this->appointment->status)->toBe('appointed');

    $this->actingAs($this->pm)->post("/appointments/{$this->appointment->ulid}/verify")->assertSessionHas('success');
    expect($this->appointment->fresh()->registration_verified_at)->not->toBeNull();
});

it('takes a fee claim through approval by the QS and payment by finance', function (): void {
    $this->actingAs($this->pm)->post("/appointments/{$this->appointment->ulid}/claims", [
        'claim_number' => '001', 'description' => 'Stage 2 concept design', 'amount' => 250000, 'submitted_on' => now()->toDateString(),
    ])->assertSessionHas('success');
    $claim = inCompany($this->company, fn () => FeeClaim::query()->firstOrFail());

    $this->actingAs($this->finance)->patch("/fee-claims/{$claim->id}", ['status' => 'paid'])->assertSessionHas('error');
    $this->actingAs($this->pm)->patch("/fee-claims/{$claim->id}", ['status' => 'approved'])->assertForbidden();

    $this->actingAs($this->qs)->patch("/fee-claims/{$claim->id}", ['status' => 'approved'])->assertSessionHas('success');
    $this->actingAs($this->finance)->patch("/fee-claims/{$claim->id}", ['status' => 'paid'])->assertSessionHas('success');

    expect($claim->fresh()->status)->toBe(ClaimStatus::Paid)->and($claim->fresh()->approved_by)->toBe($this->qs->id);
});

it('flags approved claims that exceed the agreed fee', function (): void {
    inCompany($this->company, fn () => FeeClaim::query()->forceCreate([
        'professional_appointment_id' => $this->appointment->id, 'claim_number' => '009', 'amount' => 1200000,
        'submitted_on' => now()->toDateString(), 'status' => 'approved',
    ]));

    $this->actingAs($this->pm)->get("/projects/{$this->project->ulid}/team")
        ->assertInertia(fn (Assert $page) => $page->component('projects/team')->where('appointments.0.overClaimed', true));
});

it('requires a percentage for percentage-based fees', function (): void {
    $this->actingAs($this->pm)->post("/projects/{$this->project->ulid}/team", [
        'discipline' => 'quantity_surveyor', 'firm_name' => 'Dlamini QS', 'fee_basis' => 'percentage',
    ])->assertSessionHasErrors('fee_percentage');
});
