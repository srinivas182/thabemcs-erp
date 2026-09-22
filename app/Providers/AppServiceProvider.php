<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

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

        // Company Admins manage the people in their own company.
        Gate::define('manage-company-users', static fn (User $user): bool => $user->hasRole(Role::CompanyAdmin->value));

        // Projects: who can create and run projects, and who can approve stage gates.
        Gate::define('manage-projects', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value,
        ]));
        Gate::define('approve-stage-gate', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value,
        ]));

        // Feasibility and funding: development, finance and leadership roles.
        Gate::define('manage-feasibility', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::Finance->value,
        ]));
        Gate::define('manage-funding', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::Finance->value,
        ]));

        // Land acquisition, professional team and fee claims.
        Gate::define('manage-land', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value,
        ]));
        Gate::define('manage-team', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value,
        ]));
        Gate::define('approve-fee-claims', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::QuantitySurveyor->value,
        ]));

        // Supplier registry and document management.
        Gate::define('manage-suppliers', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::Procurement->value,
        ]));
        Gate::define('manage-documents', static fn (User $user): bool => $user->hasAnyRole(array_values(array_map(
            static fn (Role $r): string => $r->value,
            array_filter(Role::cases(), static fn (Role $r): bool => $r !== Role::Contractor),
        ))));

        // Site capture (site app), site management and health & safety.
        Gate::define('capture-site', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value,
            Role::SiteManager->value, Role::SafetyOfficer->value, Role::QuantitySurveyor->value,
        ]));
        Gate::define('manage-site', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::SiteManager->value,
        ]));
        Gate::define('manage-safety', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::ProjectManager->value, Role::SiteManager->value, Role::SafetyOfficer->value,
        ]));

        // Procurement: who can ask to buy, and who runs quotes, orders and issuing.
        Gate::define('raise-requisitions', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value,
            Role::SiteManager->value, Role::QuantitySurveyor->value, Role::Procurement->value,
        ]));
        Gate::define('manage-procurement', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::Procurement->value,
        ]));

        // Company Admins and Directors can read their company's audit trail.
        Gate::define('view-audit-log', static fn (User $user): bool => $user->hasAnyRole([Role::CompanyAdmin->value, Role::Director->value]));

        // Every audit entry records the company it belongs to, so company audit trails stay isolated.
        Activity::creating(static function (Activity $activity): void {
            if ($activity->getAttribute('company_id') !== null) {
                return;
            }

            $subject = $activity->subject;
            $companyId = app(CurrentCompany::class)->id()
                ?? match (true) {
                    $subject instanceof Company => $subject->getKey(),
                    $subject instanceof User => $subject->company_id,
                    default => null,
                };

            $activity->setAttribute('company_id', $companyId);
        });

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }
}
