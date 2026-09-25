<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Enums\Module;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Data shared with every page of the management app.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $company = app(CurrentCompany::class)->get();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'timezone' => config('app.display_timezone'),
            ],
            'brand' => [
                'name' => config('branding.name'),
                'shortName' => config('branding.short_name'),
                'owner' => config('branding.owner'),
                'tagline' => config('branding.tagline'),
                'logo' => config('branding.logo') ?: null,
                'supportEmail' => config('branding.support.email') ?: null,
                'supportPhone' => config('branding.support.phone') ?: null,
                'supportHours' => config('branding.support.hours') ?: null,
                'privacyUrl' => config('branding.privacy_url') ?: null,
                'poweredBy' => config('branding.powered_by') ?: null,
            ],
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->ulid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'jobTitle' => $user->job_title,
                    'isSuperAdmin' => $user->is_super_admin,
                    'roles' => $user->is_super_admin ? ['super-admin'] : $user->getRoleNames()->all(),
                ] : null,
            ],
            'help' => $this->helpFor($request),
            'can' => [
                'manageCompanies' => $user instanceof User && $user->is_super_admin,
                'manageForms' => $user instanceof User && $user->can('manage-forms'),
                'manageIntegrations' => $user instanceof User && $user->can('manage-integrations'),
                'viewSales' => $user instanceof User && $user->can('view-sales'),
                'manageSales' => $user instanceof User && $user->can('manage-sales'),
                'viewRentals' => $user instanceof User && $user->can('view-rentals'),
                'manageRentals' => $user instanceof User && $user->can('manage-rentals'),
                'manageContent' => $user instanceof User && $user->can('manage-content'),
                'publishContent' => $user instanceof User && $user->can('publish-content'),
                'managePopia' => $user instanceof User && $user->can('manage-popia'),
                'manageMasterData' => $user instanceof User && $user->can('manage-master-data'),
                'viewPortfolio' => $user instanceof User && ($user->is_super_admin || $user->can('view-financial-reports')),
                'manageUsers' => $user instanceof User && $company !== null && $user->can('manage-company-users'),
                'viewAuditLog' => $user instanceof User && $user->can('view-audit-log'),
            ],
            'notifications' => [
                'unread' => fn (): int => $user instanceof User ? $user->unreadNotifications()->count() : 0,
            ],
            'company' => $company ? [
                'id' => $company->ulid,
                'name' => $company->name,
                'modules' => array_map(static fn (Module $m): array => [
                    'key' => $m->value,
                    'label' => $m->label(),
                ], $company->enabledModules()),
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Help for the page being shown, matched on the first part of the path.
     *
     * @return array{title: string, body: string, steps: list<string>}|null
     */
    private function helpFor(Request $request): ?array
    {
        /** @var array<string, array{title: string, body: string, steps: list<string>}> $help */
        $help = (array) config('help');
        $path = trim($request->path(), '/');

        foreach ($help as $prefix => $entry) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $entry;
            }
        }

        return null;
    }
}
