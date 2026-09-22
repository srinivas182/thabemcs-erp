<?php

declare(strict_types=1);

namespace App\Domains\Contracts\Http\Controllers;

use App\Domains\Contracts\Models\Contract;
use App\Domains\Contracts\Models\PaymentCertificate;
use App\Domains\Contracts\Services\CertificateService;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class ContractController
{
    public const array FORMS = [
        'jbcc_pba' => 'JBCC Principal Building Agreement', 'jbcc_mwa' => 'JBCC Minor Works Agreement', 'nec4_ecc' => 'NEC4 Engineering and Construction Contract',
        'gcc_2015' => 'GCC 2015 (public sector)', 'fidic' => 'FIDIC', 'other' => 'Own form of contract',
    ];

    public function __construct(
        private readonly CertificateService $certificates,
        private readonly ComplianceService $compliance,
        private readonly CurrentCompany $context,
    ) {}

    public function show(Request $request, Project $project): Response
    {
        $contracts = Contract::query()->with(['supplier', 'certificates' => fn ($q) => $q->orderByDesc('number')])->where('project_id', $project->id)->get();

        return Inertia::render('projects/contracts', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'contracts' => $contracts->map(fn (Contract $c): array => [
                'id' => $c->ulid, 'reference' => $c->reference, 'form' => self::FORMS[$c->contract_form] ?? $c->contract_form,
                'contractor' => $c->supplier->name, 'sum' => (float) $c->contract_sum, 'retention' => (float) $c->retention_percent,
                'cap' => $c->retention_cap_percent === null ? null : (float) $c->retention_cap_percent, 'release' => (float) $c->release_at_practical_percent,
                'practical' => $c->practical_completion_on?->toDateString(), 'final' => $c->final_completion_on?->toDateString(),
                'paymentDays' => $c->payment_terms_days, 'defectsMonths' => $c->defects_period_months,
                'position' => $this->certificates->position($c), 'blockers' => $this->compliance->blockers($c->supplier, (float) $c->contract_sum * 1.15),
                'certificates' => $c->certificates->map(static fn (PaymentCertificate $p): array => [
                    'id' => $p->ulid, 'reference' => $p->reference(), 'date' => $p->valuation_date->toDateString(), 'gross' => (float) $p->gross_value,
                    'held' => (float) $p->retention_held, 'released' => (float) $p->retention_released, 'previous' => (float) $p->previous_certified,
                    'due' => (float) $p->amount_due, 'vat' => (float) $p->vat, 'status' => $p->status,
                ])->values(),
            ]),
            'contractors' => Supplier::query()->whereIn('type', ['contractor', 'subcontractor'])->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (Supplier $s): array => ['key' => $s->ulid, 'label' => $s->name])->values(),
            'budgetLines' => BudgetLine::query()->where('project_id', $project->id)->orderBy('code')->get(['id', 'code', 'description'])
                ->map(static fn (BudgetLine $l): array => ['key' => (string) $l->id, 'label' => "{$l->code} {$l->description}"])->values(),
            'forms' => collect(self::FORMS)->map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
            'formDefaults' => config('contract_forms'),
            'canManage' => $request->user()?->can('manage-contracts') ?? false,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-contracts');
        $data = $request->validate([
            'supplier' => ['required', 'string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'reference' => ['required', 'string', 'max:60', Rule::unique('contracts')->where('project_id', $project->id)],
            'contract_form' => ['required', Rule::in(array_keys(self::FORMS))],
            'contract_sum' => ['required', 'numeric', 'gt:0'],
            'retention_percent' => ['required', 'numeric', 'between:0,20'],
            'retention_cap_percent' => ['nullable', 'numeric', 'between:0,20'],
            'release_at_practical_percent' => ['required', 'numeric', 'between:0,100'],
            'payment_terms_days' => ['nullable', 'integer', 'between:0,120'],
            'defects_period_months' => ['nullable', 'integer', 'between:0,60'],
            'budget_line_id' => ['nullable', 'integer', Rule::exists('budget_lines', 'id')->where('project_id', $project->id)],
        ]);

        $supplier = Supplier::query()->where('ulid', $data['supplier'])->firstOrFail();
        $reasons = $this->compliance->blockers($supplier, (float) $data['contract_sum'] * 1.15);
        if ($reasons !== []) {
            return back()->with('error', "{$supplier->name} cannot be appointed: ".implode('; ', $reasons).'.');
        }

        unset($data['supplier']);
        Contract::query()->create([...$data, 'project_id' => $project->id, 'supplier_id' => $supplier->id]);

        return back()->with('success', "Contract {$data['reference']} with {$supplier->name} recorded.");
    }

    public function completion(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('manage-contracts');
        $data = $request->validate([
            'practical_completion_on' => ['nullable', 'date', 'before_or_equal:today'],
            'final_completion_on' => ['nullable', 'date', 'after_or_equal:practical_completion_on', 'before_or_equal:today'],
        ]);
        $contract->update([...$data, 'status' => ($data['final_completion_on'] ?? null) ? 'final_completion' : (($data['practical_completion_on'] ?? null) ? 'practical_completion' : 'active')]);

        return back()->with('success', 'Completion dates saved. The next certificate will release retention accordingly.');
    }

    public function prepare(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('manage-contracts');
        $data = $request->validate([
            'gross_value' => ['required', 'numeric', 'min:0'],
            'valuation_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $user */
        $user = $request->user();

        try {
            $certificate = $this->certificates->prepare($contract, (float) $data['gross_value'], Carbon::parse((string) $data['valuation_date']), $data['notes'] ?? null, $user);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$certificate->reference()} prepared: R".number_format((float) $certificate->amount_due, 2, '.', ' ').' due excl. VAT. Check it, then submit for approval.');
    }

    public function submit(Request $request, PaymentCertificate $certificate): RedirectResponse
    {
        Gate::authorize('manage-contracts');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->certificates->submit($certificate, $user);
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$certificate->reference()} submitted for approval.");
    }
}
