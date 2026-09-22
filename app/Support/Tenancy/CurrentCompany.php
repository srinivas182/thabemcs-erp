<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domains\Platform\Models\Company;
use App\Support\Tenancy\Exceptions\MissingCompanyContextException;

/**
 * Holds the company (tenant) that the current request or job is acting for.
 *
 * Registered as a *scoped* binding so it is reset between requests under
 * Laravel Octane. All company-owned models are filtered through this context
 * by {@see CompanyScope}; when no company is set the scope fails closed and
 * returns no rows, unless the context has been explicitly opened for a
 * platform-level (Super Admin) operation via {@see self::runAsPlatform()}.
 */
final class CurrentCompany
{
    private ?Company $company = null;

    private bool $platformAccess = false;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    /**
     * Return the current company or throw when none is set.
     *
     * @throws MissingCompanyContextException
     */
    public function require(): Company
    {
        return $this->company ?? throw new MissingCompanyContextException;
    }

    /**
     * Whether platform-wide (cross-company) access is currently open.
     */
    public function hasPlatformAccess(): bool
    {
        return $this->platformAccess;
    }

    /**
     * Allow cross-company reads for the whole request (Super Admin only).
     */
    public function grantPlatformAccess(): void
    {
        $this->platformAccess = true;
    }

    /**
     * Run a callback with platform-wide access, restoring the previous state afterwards.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAsPlatform(callable $callback): mixed
    {
        $previous = $this->platformAccess;
        $this->platformAccess = true;

        try {
            return $callback();
        } finally {
            $this->platformAccess = $previous;
        }
    }

    /**
     * Run a callback in the context of the given company, restoring the previous context afterwards.
     * Used by queued jobs and console commands.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
