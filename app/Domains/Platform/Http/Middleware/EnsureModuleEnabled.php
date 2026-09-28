<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Middleware;

use App\Domains\Platform\Enums\Module;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a module the Super Admin has not enabled for the current company.
 *
 * This is a commercial switch - which modules a customer has - not a security control, so a Super Admin
 * working inside a company passes through it. They need to see the whole instance to support it, and the
 * permission gates still apply to them as they do to anyone.
 *
 * Usage: Route::middleware('module:finance')
 */
final class EnsureModuleEnabled
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $moduleEnum = Module::from($module);
        $company = $this->context->get();
        $user = $request->user();

        if ($user instanceof User && $user->is_super_admin) {
            return $next($request);
        }

        if ($company === null || ! $company->hasModule($moduleEnum)) {
            abort(Response::HTTP_FORBIDDEN, sprintf('%s is not enabled for this company.', $moduleEnum->label()));
        }

        return $next($request);
    }
}
