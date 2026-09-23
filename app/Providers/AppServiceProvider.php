<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Models\VariationOrder;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Reporting\Jobs\RefreshProjectMetrics;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Models\Snag;
use App\Domains\Workforce\Services\PublicHolidays;
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
        $this->app->singleton(PublicHolidays::class);
        // Scoped (not singleton) so the company context resets between requests under Octane.
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Records whose changes affect a project's stored metrics.
     *
     * @var list<class-string<Model>>
     */
    private const array METRIC_SOURCES = [
        Project::class,
        Risk::class,
        BudgetLine::class,
        SupplierInvoice::class,
        VariationOrder::class,
        PurchaseOrder::class,
        SafetyIncident::class,
        Snag::class,
        ProgrammeActivity::class,
        ActivityDependency::class,
    ];

    public function boot(): void
    {
        // Keep precomputed project figures current without recalculating them on every page view.
        foreach (self::METRIC_SOURCES as $model) {
            $model::saved(static fn ($record) => RefreshProjectMetrics::forModel($record));
            $model::deleted(static fn ($record) => RefreshProjectMetrics::forModel($record));
        }

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

        // Finance: budgets, variations, invoices and payments.
        Gate::define('manage-budget', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::QuantitySurveyor->value, Role::Finance->value,
        ]));
        Gate::define('raise-variations', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::QuantitySurveyor->value,
        ]));
        Gate::define('manage-finance', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::Finance->value,
        ]));
        Gate::define('override-invoice-match', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value,
        ]));

        // Contracts, workforce and plant.
        Gate::define('manage-contracts', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::QuantitySurveyor->value,
        ]));
        Gate::define('manage-workforce', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::SiteManager->value,
        ]));
        Gate::define('approve-leave', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::ProjectManager->value,
        ]));
        Gate::define('manage-plant', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::SiteManager->value, Role::Procurement->value,
        ]));

        // Company master data (cost code library, units).
        Gate::define('manage-master-data', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Finance->value, Role::QuantitySurveyor->value,
        ]));

        // POPIA: information officer tasks (register, retention, data subject requests).
        Gate::define('manage-popia', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value,
        ]));

        // Sales.
        Gate::define('view-sales', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::SalesAndLeasing->value, Role::Finance->value,
        ]));
        Gate::define('manage-sales', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::SalesAndLeasing->value,
        ]));
        Gate::define('approve-commission', static fn (User $user): bool => $user->hasAnyRole([
            Role::Director->value, Role::Finance->value,
        ]));

        // Closing a project out and approving investor distributions.
        Gate::define('close-projects', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value,
        ]));

        // Rentals.
        Gate::define('view-rentals', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::SalesAndLeasing->value, Role::Finance->value,
        ]));
        Gate::define('manage-rentals', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::SalesAndLeasing->value,
        ]));

        // Form builder and integrations.
        Gate::define('manage-forms', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::DevelopmentManager->value, Role::ProjectManager->value, Role::SafetyOfficer->value,
        ]));
        Gate::define('manage-integrations', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Finance->value,
        ]));

        // Reports and dashboards.
        Gate::define('view-financial-reports', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::Finance->value,
            Role::QuantitySurveyor->value, Role::ProjectManager->value,
        ]));
        Gate::define('view-safety-reports', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::DevelopmentManager->value, Role::ProjectManager->value,
            Role::SiteManager->value, Role::SafetyOfficer->value,
        ]));
        Gate::define('manage-report-schedules', static fn (User $user): bool => $user->hasAnyRole([
            Role::CompanyAdmin->value, Role::Director->value, Role::Finance->value,
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
