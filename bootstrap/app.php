<?php

declare(strict_types=1);

use App\Domains\Platform\Http\Middleware\EnsureModuleEnabled;
use App\Domains\Platform\Http\Middleware\EnsureSuperAdmin;
use App\Domains\Platform\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    // Commands live with the domain they belong to, not in app/Console.
    ->withCommands([__DIR__.'/../app/Domains/Platform/Console'])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            SetCurrentCompany::class,
            RequireTwoFactor::class,
            HandleInertiaRequests::class,
        ]);

        // Security headers and a request id on everything, including the API and public links.
        $middleware->append([AddRequestId::class, SecurityHeaders::class]);

        // The company context must be set before route-model binding, otherwise company-owned
        // records in the URL (e.g. /projects/{project}) resolve with no company and return 404.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentCompany::class);

        $middleware->alias([
            'company' => SetCurrentCompany::class,
            'module' => EnsureModuleEnabled::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
