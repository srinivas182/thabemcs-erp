<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http\Requests;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rules\Exists;

/**
 * People are referenced by their public ULID and must be active users of the current company.
 */
trait ResolvesCompanyUsers
{
    protected function companyUserRule(): Exists
    {
        return (new Exists('users', 'ulid'))
            ->where('company_id', app(CurrentCompany::class)->id())
            ->where('is_active', true);
    }

    protected function userIdFor(string $key): ?int
    {
        if (! $this->filled($key)) {
            return null;
        }

        return User::query()->where('ulid', $this->string($key)->toString())->value('id');
    }
}
