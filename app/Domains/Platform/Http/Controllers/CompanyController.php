<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Enums\CompanyStatus;
use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Http\Requests\CompanyRequest;
use App\Domains\Platform\Http\Requests\StoreCompanyRequest;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Services\CompanyService;
use App\Domains\Platform\Services\QuotaService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin: companies, their limits and enabled modules.
 */
final class CompanyController
{
    public function __construct(
        private readonly CompanyService $companies,
        private readonly QuotaService $quotas,
    ) {}

    public function index(): Response
    {
        $companies = Company::query()->orderBy('name')->get()->map(fn (Company $company): array => [
            'id' => $company->ulid,
            'name' => $company->name,
            'legalName' => $company->legal_name,
            'status' => $company->status->value,
            'modulesEnabled' => count($company->modules ?? []),
            'usage' => $this->quotas->summary($company),
        ]);

        return Inertia::render('platform/companies/index', [
            'companies' => $companies,
            'moduleCount' => count(Module::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/companies/form', [
            'company' => null,
            'modules' => $this->moduleOptions(),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $company = $this->companies->create($request->companyData(), $request->adminData(), $user);
        } catch (QuotaExceededException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('platform.companies.index')
            ->with('success', "{$company->name} created. An invitation was emailed to {$request->adminData()['email']}.");
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('platform/companies/form', [
            'company' => [
                'id' => $company->ulid,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'registration_number' => $company->registration_number,
                'vat_number' => $company->vat_number,
                'max_projects' => $company->max_projects,
                'max_users' => $company->max_users,
                'max_storage_mb' => $company->max_storage_mb,
                'modules' => $company->modules ?? [],
                'status' => $company->status->value,
            ],
            'modules' => $this->moduleOptions(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->companies->update($company, $request->companyData());

        return redirect()->route('platform.companies.index')->with('success', "{$company->name} updated.");
    }

    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $status = CompanyStatus::from((string) $validated['status']);

        /** @var User $user */
        $user = $request->user();
        $this->companies->changeStatus($company, $status, $user);

        return back()->with('success', $status === CompanyStatus::Suspended
            ? "{$company->name} is suspended. Its users can no longer sign in."
            : "{$company->name} is active again.");
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function moduleOptions(): array
    {
        return array_map(static fn (Module $m): array => ['key' => $m->value, 'label' => $m->label()], Module::cases());
    }
}
