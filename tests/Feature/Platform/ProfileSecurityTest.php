<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->forCompany(Company::factory()->create())->withoutTwoFactor()->create();
});

it('shows the profile with two-factor status', function (): void {
    $this->actingAs($this->user)->get('/settings/profile')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/profile')
            ->where('profile.email', $this->user->email)
            ->where('twoFactor.enabled', false));
});

it('updates name, email and a South African mobile number', function (): void {
    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'Lerato Nkosi', 'email' => 'Lerato@Example.test', 'phone' => '+27 82 123 4567',
    ])->assertSessionHasNoErrors();

    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('Lerato Nkosi')
        ->and($fresh->email)->toBe('lerato@example.test')
        ->and($fresh->phone)->toBe('+27821234567');
});

it('rejects a phone number that is not South African', function (): void {
    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'X', 'email' => $this->user->email, 'phone' => '12345',
    ])->assertSessionHasErrors('phone');
});

it('changes the password only with the current password', function (): void {
    $this->actingAs($this->user)->put('/user/password', [
        'current_password' => 'wrong', 'password' => 'N3w-Passw0rd!', 'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($this->user)->put('/user/password', [
        'current_password' => 'password', 'password' => 'N3w-Passw0rd!', 'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('N3w-Passw0rd!', $this->user->fresh()->password))->toBeTrue();
});

it('asks for the password again before turning on two-factor authentication', function (): void {
    $this->actingAs($this->user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertRedirect();

    expect($this->user->fresh()->two_factor_secret)->not->toBeNull()
        ->and($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
});
