<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Models\User;

it('shows the sign-in screen', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('signs in an active user and records the login time', function (): void {
    $user = User::factory()->forCompany(Company::factory()->create())->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('refuses to sign in a deactivated user', function (): void {
    $user = User::factory()->forCompany(Company::factory()->create())->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});
