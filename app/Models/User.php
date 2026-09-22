<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Platform\Models\Company;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who signs in to the platform.
 *
 * Company users belong to exactly one company. Super Admins have no company
 * and manage the platform (companies, quotas, modules).
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $company_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property bool $is_super_admin
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property-read Company|null $company
 */
#[Fillable(['name', 'email', 'password', 'phone', 'job_title'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The company a Super Admin has chosen to act as (stored in the session), if any.
     */
    public function actingCompany(Request $request): ?Company
    {
        if (! $this->is_super_admin || ! $request->hasSession()) {
            return null;
        }

        $companyId = $request->session()->get('acting_company_id');

        if (! is_int($companyId)) {
            return null;
        }

        return Company::query()->find($companyId);
    }
}
