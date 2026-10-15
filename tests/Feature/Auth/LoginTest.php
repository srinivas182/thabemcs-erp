<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Models\User;

it('shows the sign-in screen', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('signs in an active user and records the login time', function (): void {
    $user = User::factory()->forCompany(Company::factory()->create())->withoutTwoFactor()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/my-day');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('refuses to sign in a deactivated user', function (): void {
    $user = User::factory()->forCompany(Company::factory()->create())->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

it('lands in the back office after signing in, even when the visitor came from the website', function (): void {
    $user = User::factory()->forCompany(Company::factory()->create())->withoutTwoFactor()->create();

    // Arriving from a public page sets it as the intended address; it must not be honoured.
    $this->withSession(['url.intended' => '/developments'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/my-day');

    auth()->logout();

    // A page that is behind sign-in is still honoured, so deep links keep working.
    $this->withSession(['url.intended' => '/projects'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/projects');
});
