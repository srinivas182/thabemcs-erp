<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a Super Admin choose which company to act as (or return to platform view).
 */
final class ActingCompanyController
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $request->session()->put('acting_company_id', $company->getKey());

        activity('platform')
            ->causedBy($request->user())
            ->performedOn($company)
            ->log('Super Admin started acting as company');

        return redirect()->route('my-day')->with('success', "You are now working in {$company->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $request->session()->forget('acting_company_id');

        return redirect()->route('my-day')->with('success', 'You are back in the platform view.');
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_super_admin, 403);
    }
}
