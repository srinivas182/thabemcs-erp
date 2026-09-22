<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Enums\CompanyStatus;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin operations on companies.
 */
final class CompanyService
{
    public function __construct(private readonly UserInvitationService $invitations) {}

    /**
     * Create a company and invite its first Company Admin.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{name: string, email: string}  $admin
     */
    public function create(array $attributes, array $admin, User $createdBy): Company
    {
        return DB::transaction(function () use ($attributes, $admin, $createdBy): Company {
            $company = Company::query()->create([...$attributes, 'status' => CompanyStatus::Active]);

            $this->invitations->invite($company, [...$admin, 'job_title' => 'Company Administrator'], Role::CompanyAdmin, $createdBy);

            return $company;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Company $company, array $attributes): Company
    {
        $company->update($attributes);

        return $company;
    }

    public function changeStatus(Company $company, CompanyStatus $status, User $by): void
    {
        $company->update(['status' => $status]);

        activity('platform')
            ->causedBy($by)
            ->performedOn($company)
            ->log($status === CompanyStatus::Suspended ? 'Company suspended' : 'Company reactivated');
    }
}
