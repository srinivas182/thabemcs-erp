<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts platform administration (companies, quotas, modules) to Super Admins.
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_super_admin, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
