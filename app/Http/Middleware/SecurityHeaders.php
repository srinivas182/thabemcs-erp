<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, including a content security policy.
 *
 * The policy allows scripts only from this site (plus the Vite dev server when developing), which stops
 * an injected script from running even if something slipped past output escaping. Inline scripts need a
 * nonce, shared with the page through Inertia.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $dev = app()->environment('local');
        $vite = $dev ? ' http://localhost:5173 ws://localhost:5173' : '';

        $policy = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".($dev ? " 'unsafe-eval'" : '').$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            // Map tiles and uploaded images; blob: is needed for photos taken on the site app.
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
            "connect-src 'self'".$vite.' https://api.open-meteo.com',
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        $headers = [
            'Content-Security-Policy' => implode('; ', $policy),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Tell browsers to stay on HTTPS, but only once we are actually on it.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        return $response;
    }
}
