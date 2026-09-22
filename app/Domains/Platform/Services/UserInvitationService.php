<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates company users and emails them an invitation to set their password.
 */
final class UserInvitationService
{
    public function __construct(private readonly QuotaService $quotas) {}

    /**
     * @param  array{name: string, email: string, job_title?: string|null, phone?: string|null}  $data
     *
     * @throws QuotaExceededException
     */
    public function invite(Company $company, array $data, Role $role, ?User $invitedBy = null): User
    {
        $this->quotas->ensureCanAdd($company, QuotaType::Users);

        $user = DB::transaction(function () use ($company, $data, $role): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                // Unusable random password until the user sets their own through the invitation link.
                'password' => Str::password(40),
            ]);
            $user->forceFill(['company_id' => $company->getKey(), 'is_super_admin' => false, 'is_active' => true])->save();

            $this->withinCompanyTeam($company, fn () => $user->assignRole($role->value));

            return $user;
        });

        $token = Password::broker()->createToken($user);
        $user->notify(new UserInvitation($token, $company->name, $invitedBy?->name));

        activity('users')
            ->causedBy($invitedBy)
            ->performedOn($user)
            ->withProperties(['company_id' => $company->getKey(), 'role' => $role->value])
            ->log('User invited');

        return $user;
    }

    /**
     * Replace the user's role within their company.
     */
    public function changeRole(User $user, Role $role): void
    {
        $company = $user->company;

        if ($company === null) {
            return;
        }

        $this->withinCompanyTeam($company, fn () => $user->syncRoles([$role->value]));
    }

    /**
     * Role assignments are stored per company (spatie teams), so the team must match the user's company.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withinCompanyTeam(Company $company, callable $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($company->getKey());

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
