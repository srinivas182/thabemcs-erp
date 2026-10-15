<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where somebody lands after signing in.
 *
 * Laravel normally returns them to the page they were trying to reach. Since the public website shares
 * this application, that page is often a marketing page - and sending a signed-in person there drops them
 * out of the back office, or, on an Inertia request, renders the website inside it.
 *
 * So an intended page is only honoured when it is itself behind sign-in.
 */
final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $home = (string) config('fortify.home', '/my-day');
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isBehindSignIn($intended)) {
            return $this->redirect($request, $intended);
        }

        return $this->redirect($request, $home);
    }

    private function redirect(Request $request, string $to): RedirectResponse
    {
        return $request->wantsJson()
            ? new RedirectResponse($to)
            : redirect()->to($to);
    }

    /** True when the address belongs to a route that requires signing in. */
    private function isBehindSignIn(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return false;
        }

        try {
            /** @var Route $route */
            $route = Router::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && (str_starts_with($middleware, 'auth') || str_contains($middleware, 'Authenticate'))) {
                return true;
            }
        }

        return false;
    }
}
