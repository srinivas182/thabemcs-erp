<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Documents\Models\DocumentVersion;
use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CompanyScope;

/**
 * Enforces the per-company limits set by the Super Admin.
 */
final class QuotaService
{
    public function usage(Company $company, QuotaType $quota): int
    {
        return match ($quota) {
            QuotaType::Projects => Project::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $company->getKey())
                ->count(),
            QuotaType::Users => User::query()
                ->where('company_id', $company->getKey())
                ->count(),
            QuotaType::StorageMb => (int) ceil((int) DocumentVersion::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $company->getKey())
                ->sum('size_bytes') / 1_048_576),
        };
    }

    public function limit(Company $company, QuotaType $quota): ?int
    {
        /** @var int|null $limit */
        $limit = $company->getAttribute($quota->column());

        return $limit;
    }

    public function remaining(Company $company, QuotaType $quota): ?int
    {
        $limit = $this->limit($company, $quota);

        return $limit === null ? null : max(0, $limit - $this->usage($company, $quota));
    }

    /**
     * @throws QuotaExceededException
     */
    public function ensureCanAdd(Company $company, QuotaType $quota, int $count = 1): void
    {
        $limit = $this->limit($company, $quota);

        if ($limit !== null && $this->usage($company, $quota) + $count > $limit) {
            throw new QuotaExceededException($quota, $limit);
        }
    }

    /**
     * Storage is checked in bytes so that small files are not rounded away.
     *
     * @throws QuotaExceededException
     */
    public function ensureStorageFor(Company $company, int $bytes): void
    {
        $limit = $this->limit($company, QuotaType::StorageMb);

        if ($limit === null) {
            return;
        }

        $used = (int) DocumentVersion::query()->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->getKey())->sum('size_bytes');

        if ($used + $bytes > $limit * 1_048_576) {
            throw new QuotaExceededException(QuotaType::StorageMb, $limit);
        }
    }

    /**
     * Usage summary for the company admin screen.
     *
     * @return array<string, array{used: int, limit: int|null}>
     */
    public function summary(Company $company): array
    {
        $summary = [];

        foreach (QuotaType::cases() as $quota) {
            $summary[$quota->value] = [
                'used' => $this->usage($company, $quota),
                'limit' => $this->limit($company, $quota),
            ];
        }

        return $summary;
    }
}
