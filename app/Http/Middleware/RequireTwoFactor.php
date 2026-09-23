<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * People who can move money, change permissions or approve work must have two-factor authentication
 * switched on. They can still reach their own security settings to set it up, and nothing else.
 */
final class RequireTwoFactor
{
    private const array ALLOWED = ['profile', 'logout', 'user/two-factor*', 'user/confirmed-two-factor*', 'user/confirm-password', 'health'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->two_factor_confirmed_at !== null || ! $this->mustHaveIt($user)) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED)) {
            return $next($request);
        }

        $message = 'Your role can approve work and move money, so two-factor authentication is required. Set it up to carry on.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : redirect()->to(route('profile'))->with('error', $message);
    }

    private function mustHaveIt(User $user): bool
    {
        /** @var list<string> $roles */
        $roles = (array) config('platform.two_factor_required_roles', []);

        return $user->is_super_admin || $user->hasAnyRole($roles);
    }
}
