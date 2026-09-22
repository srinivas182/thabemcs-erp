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
}
