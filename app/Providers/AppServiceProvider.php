<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped (not singleton) so the company context resets between requests under Octane.
        $this->app->scoped(CurrentCompany::class);
    }

    public function boot(): void
    {
        // Catch lazy loading, silently discarded attributes and missing attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admins pass every authorisation check; company users go through roles and policies.
        Gate::before(static fn (User $user): ?bool => $user->is_super_admin ? true : null);

        Password::defaults(static fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }
}
