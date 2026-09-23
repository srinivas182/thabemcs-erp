<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id that appears in the logs and in the response header, so a report of
 * "something went wrong at 14:32" can be traced to exactly one request across the servers.
 */
final class AddRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) ($request->header('X-Request-Id') ?: Str::uuid());
        $request->attributes->set('request_id', $id);

        Log::shareContext([
            'request_id' => $id,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
