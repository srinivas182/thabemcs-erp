<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Middleware;

use App\Domains\Platform\Enums\Module;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a module the Super Admin has not enabled for the current company.
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

        if ($company === null || ! $company->hasModule($moduleEnum)) {
            abort(Response::HTTP_FORBIDDEN, sprintf('%s is not enabled for this company.', $moduleEnum->label()));
        }

        return $next($request);
    }
}
