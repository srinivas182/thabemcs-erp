<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('sends visitors who are not signed in to the sign-in page', function (): void {
    $this->get('/')->assertRedirect('/login');
});

it('shows the configured branding and support contact on the sign-in page', function (): void {
    config([
        'branding.short_name' => 'Thabekhulu',
        'branding.support.email' => 'support@example.co.za',
        'branding.support.phone' => '031 000 0000',
        'branding.logo' => null,
    ]);

    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login')
        ->where('brand.shortName', 'Thabekhulu')
        ->where('brand.supportEmail', 'support@example.co.za')
        ->where('brand.supportPhone', '031 000 0000')
        ->where('brand.logo', null));
});

it('passes status messages such as a completed password reset to the sign-in page', function (): void {
    $this->withSession(['status' => 'Your password has been reset.'])->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('status', 'Your password has been reset.'));
});

it('asks search engines not to index the system', function (): void {
    expect((string) file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
    $this->get('/login')->assertSee('noindex, nofollow', false);
});
