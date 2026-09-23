<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ETags for the site app's read endpoints. Phones on site re-check cheaply: unchanged lists come back
 * as 304 Not Modified with no body, which saves both data on the phone and work on the server.
 */
final class SetCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $etag = '"'.md5((string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');

        if (in_array($etag, array_map(trim(...), explode(',', (string) $request->header('If-None-Match'))), true)) {
            $response->setStatusCode(304);
            $response->setContent(null);
        }

        return $response;
    }
}
