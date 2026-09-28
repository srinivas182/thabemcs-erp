<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Proves that nothing is reachable without signing in except the handful of addresses that are meant to
 * be public. When somebody adds a route and forgets the middleware, this test fails before it ships.
 */
it('keeps every route behind sign-in except the ones the public is meant to reach', function (): void {
    // The public website, the two token links, sign-in itself, and the health check.
    // Each of these is public on purpose, and each is protected another way: the website shows only
    // published content, the two token links carry a 48-character token stored only as a hash and are
    // rate-limited, and the site app shell holds no data until its API is called with a token.
    $publicNames = ['website.home', 'website.page', 'website.developments', 'website.development',
        'website.articles', 'website.article', 'website.sitemap', 'website.forms.submit',
        'rfq.respond', 'rfq.submit', 'rfq.decline', 'tenant.portal', 'tenant.requests',
        'site-app', 'health'];
    $publicPrefixes = ['login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor',
        'user/confirm-password', 'up', 'storage', '_ignition', 'sanctum'];

    $unprotected = [];

    foreach (Route::getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $uri = $route->uri();
        $name = (string) $route->getName();
        $middleware = $route->gatherMiddleware();

        $expectedPublic = in_array($name, $publicNames, true)
            || collect($publicPrefixes)->contains(fn (string $p): bool => str_starts_with($uri, $p));

        $protected = collect($middleware)->contains(fn (mixed $m): bool => is_string($m)
            && (str_starts_with($m, 'auth') || str_contains($m, 'Authenticate')));

        if (! $protected && ! $expectedPublic) {
            $unprotected[] = $route->methods()[0].' /'.$uri.($name !== '' ? " ({$name})" : '');
        }
    }

    expect($unprotected)->toBe([], 'These routes are reachable without signing in. Add auth middleware, or add them to the list in this test with a reason: '.implode(', ', $unprotected));
});

it('rate-limits the things people attack: sign-in, the website and its forms', function (): void {
    $limits = [];

    foreach (Route::getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $middleware = implode(' ', array_filter($route->gatherMiddleware(), 'is_string'));
        if (str_starts_with($route->uri(), 'forms/')) {
            $limits['forms'] = str_contains($middleware, 'throttle');
        }
        if ($route->uri() === '/') {
            $limits['website'] = str_contains($middleware, 'throttle');
        }
        if ($route->uri() === 'search') {
            $limits['search'] = str_contains($middleware, 'throttle');
        }
    }

    ksort($limits);
    expect($limits)->toBe(['forms' => true, 'search' => true, 'website' => true]);
});

it('sends the security headers a browser needs, on public pages as well as the back office', function (): void {
    $response = $this->get('/login');

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toContain("script-src 'self'")->toContain("object-src 'none'")->toContain("frame-ancestors 'none'")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('defines its rate limiters even when routes are cached', function (): void {
    // The limiters used to live in the routing closure, which Laravel skips when routes are cached, so a
    // production deployment lost them and every public page failed. They now live in a service provider.
    foreach (['login', 'website', 'website-forms', 'api'] as $limiter) {
        expect(app(RateLimiter::class)->limiter($limiter))
            ->not->toBeNull("The [{$limiter}] rate limiter is not defined.");
    }
});
