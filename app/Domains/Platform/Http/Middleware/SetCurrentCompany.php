<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the company the authenticated user is acting for.
 *
 * - Company users always act for their own company.
 * - Super Admins have platform-wide access; they may also choose a company to act as.
 * - Users of a suspended company are signed out.
 */
final class SetCurrentCompany
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->is_super_admin) {
            $this->context->grantPlatformAccess();
            $this->context->set($user->actingCompany($request));
        } else {
            $company = $user->company;

            if ($company === null || ! $company->isActive() || ! $user->is_active) {
                Auth::guard('web')->logout();
                abort(Response::HTTP_FORBIDDEN, 'Your account is not active. Contact your company administrator.');
            }

            $this->context->set($company);
        }

        setPermissionsTeamId($this->context->id());

        return $next($request);
    }
}
