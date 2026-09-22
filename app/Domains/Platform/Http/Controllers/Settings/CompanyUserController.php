<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Http\Requests\InviteUserRequest;
use App\Domains\Platform\Http\Requests\UpdateCompanyUserRequest;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Services\QuotaService;
use App\Domains\Platform\Services\UserInvitationService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company Admin: invite people, change their role, deactivate or reactivate them.
 * Users are always limited to the current company.
 */
final class CompanyUserController
{
    public function __construct(
        private readonly CurrentCompany $context,
        private readonly UserInvitationService $invitations,
        private readonly QuotaService $quotas,
    ) {}

    public function index(): Response
    {
        Gate::authorize('manage-company-users');
        $company = $this->company();

        $users = User::query()
            ->where('company_id', $company->getKey())
            ->with('roles')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'jobTitle' => $user->job_title,
                'role' => $user->roles->first()?->name,
                'isActive' => $user->is_active,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/users/index', [
            'users' => $users,
            'roles' => array_map(static fn (Role $r): array => ['key' => $r->value, 'label' => $r->label()], Role::cases()),
            'quota' => [
                'used' => $this->quotas->usage($company, QuotaType::Users),
                'limit' => $this->quotas->limit($company, QuotaType::Users),
            ],
        ]);
    }

    public function store(InviteUserRequest $request): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        try {
            $user = $this->invitations->invite($this->company(), $request->userData(), $request->role(), $admin);
        } catch (QuotaExceededException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invitation sent to {$user->email}.");
    }

    public function update(UpdateCompanyUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($user);

        /** @var User $admin */
        $admin = $request->user();

        if ($request->has('is_active')) {
            $active = $request->boolean('is_active');

            if (! $active && $user->is($admin)) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }

            $user->forceFill(['is_active' => $active])->save();
        }

        if ($request->has('job_title')) {
            $user->update(['job_title' => $request->filled('job_title') ? $request->string('job_title')->toString() : null]);
        }

        if ($request->has('role')) {
            $this->invitations->changeRole($user, Role::from($request->string('role')->toString()));
        }

        activity('users')->causedBy($admin)->performedOn($user)
            ->withProperties($request->only(['role', 'is_active', 'job_title']))
            ->log('User updated');

        return back()->with('success', "{$user->name} updated.");
    }

    private function company(): Company
    {
        return $this->context->get() ?? abort(403, 'Choose a company to work in first.');
    }

    /**
     * Returns 404 (not 403) for users in another company, so their existence is not revealed.
     */
    private function ensureSameCompany(User $user): void
    {
        abort_unless($user->company_id !== null && $user->company_id === $this->context->id(), 404);
    }
}
