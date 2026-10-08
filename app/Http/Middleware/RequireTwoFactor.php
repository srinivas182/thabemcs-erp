<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Models\PlatformSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the instance requires two-factor authentication, everyone who signs in must set it up before they
 * can do anything else - Super Admins included. They can still reach their own profile to do it.
 *
 * The switch lives under Platform settings and is off by default, so a fresh or demonstration instance is
 * usable immediately. Turn it on before anybody's real data goes in.
 */
final class RequireTwoFactor
{
    private const array ALLOWED = ['settings/profile', 'settings/profile/*', 'logout', 'user/two-factor*', 'user/confirmed-two-factor*', 'user/confirm-password', 'health'];

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
            : redirect()->to(route('settings.profile'))->with('error', $message);
    }

    /**
     * Required of everybody, or of nobody: a Super Admin decides under Platform settings. One rule is
     * easier to reason about than a flag plus a role list, which is what this replaced.
     */
    private function mustHaveIt(User $user): bool
    {
        return PlatformSetting::requiresTwoFactor();
    }
}
