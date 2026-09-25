<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages have no signed-in user, so the company whose website this is comes from configuration.
 * Everything the website reads is then scoped to that company like any other request.
 */
final class ResolvePublicCompany
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $wanted = (string) config('cms.company', '');
        $company = $wanted !== ''
            ? Company::query()->where('ulid', $wanted)->first()
            : Company::query()->where('status', 'active')->orderBy('id')->first();

        abort_if($company === null, 503, 'The website is not set up yet.');

        return $this->context->runFor($company, static fn (): Response => $next($request));
    }
}
