<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Services\QuotaService;
use App\Domains\Projects\Models\Project;

it('blocks new projects once the company limit is reached', function (): void {
    $company = Company::factory()->withProjectLimit(2)->create();

    inCompany($company, function (): void {
        Project::factory()->count(2)->create();
        Project::factory()->create();
    });
})->throws(QuotaExceededException::class);

it('treats a null limit as unlimited', function (): void {
    $company = Company::factory()->withProjectLimit(null)->create();

    inCompany($company, fn () => Project::factory()->count(5)->create());

    expect(app(QuotaService::class)->remaining($company, QuotaType::Projects))->toBeNull();
});

it('reports usage against each limit', function (): void {
    $company = Company::factory()->withProjectLimit(10)->create();
    inCompany($company, fn () => Project::factory()->count(3)->create());

    expect(app(QuotaService::class)->summary($company)['projects'])
        ->toBe(['used' => 3, 'limit' => 10]);
});
