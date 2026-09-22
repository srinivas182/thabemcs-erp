<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

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
