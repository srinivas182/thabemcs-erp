<?php

declare(strict_types=1);

namespace App\Domains\Funding\Http\Controllers;

use App\Domains\Funding\Enums\FundingStatus;
use App\Domains\Funding\Enums\FundingType;
use App\Domains\Funding\Models\FundingMovement;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Domains\Funding\Models\ProjectBankAccount;
use App\Domains\Funding\Services\FundingSummary;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class FundingController
{
    public function __construct(private readonly FundingSummary $summary, private readonly CurrentCompany $context) {}

    public function show(Request $request, Project $project): Response
    {
        $sources = FundingSource::query()->with(['investor', 'movements'])->where('project_id', $project->id)->orderBy('type')->get();
        $account = ProjectBankAccount::query()->where('project_id', $project->id)->first();

        return Inertia::render('projects/funding', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'summary' => $this->summary->forProject($project),
            'sources' => $sources->map(static function (FundingSource $s): array {
                $in = (float) $s->movements->where('direction', 'in')->sum('amount');
                $out = (float) $s->movements->where('direction', 'out')->sum('amount');

                return [
                    'id' => $s->ulid,
                    'type' => $s->type->value,
                    'typeLabel' => $s->type->label(),
                    'name' => $s->name,
                    'investor' => $s->investor?->name,
                    'committed' => (float) $s->committed_amount,
                    'received' => $in,
                    'repaid' => $out,
                    'interestRate' => $s->interest_rate,
                    'agreementSignedOn' => $s->agreement_signed_on?->toDateString(),
                    'status' => $s->status->value,
                    'movements' => $s->movements->take(10)->map(static fn (FundingMovement $m): array => [
                        'direction' => $m->direction, 'amount' => (float) $m->amount,
                        'date' => $m->occurred_on->toDateString(), 'reference' => $m->reference,
                    ])->values(),
                ];
            }),
            'account' => $account ? [
                'bank' => $account->bank, 'accountName' => $account->account_name,
                'last4' => $account->account_last4, 'openedOn' => $account->opened_on?->toDateString(),
            ] : null,
            'types' => array_map(static fn (FundingType $t): array => ['key' => $t->value, 'label' => $t->label()], FundingType::cases()),
            'investors' => Investor::query()->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (Investor $i): array => ['key' => $i->ulid, 'label' => $i->name])->values(),
            'can' => ['manage' => $request->user()?->can('manage-funding') ?? false],
        ]);
    }

    public function storeSource(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-funding');

        $data = $request->validate([
            'type' => ['required', Rule::enum(FundingType::class)],
            'name' => ['required', 'string', 'max:160'],
            'investor' => ['nullable', 'required_if:type,investor', Rule::exists('investors', 'ulid')->where('company_id', $this->context->id())],
            'committed_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'interest_rate' => ['nullable', 'required_if:type,debt', 'numeric', 'between:0,50'],
            'agreement_signed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(FundingStatus::class)],
        ], ['investor.required_if' => 'Choose the investor.', 'interest_rate.required_if' => 'Enter the loan interest rate.']);

        FundingSource::query()->create([
            ...collect($data)->except('investor')->all(),
            'project_id' => $project->id,
            'investor_id' => isset($data['investor']) ? Investor::query()->where('ulid', $data['investor'])->value('id') : null,
        ]);

        return back()->with('success', 'Funding source added.');
    }

    public function updateSource(Request $request, FundingSource $source): RedirectResponse
    {
        Gate::authorize('manage-funding');

        $source->update($request->validate([
            'status' => ['sometimes', Rule::enum(FundingStatus::class)],
            'agreement_signed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'committed_amount' => ['sometimes', 'numeric', 'min:1', 'max:999999999999'],
        ]));

        return back();
    }

    public function storeMovement(Request $request, FundingSource $source): RedirectResponse
    {
        Gate::authorize('manage-funding');

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        FundingMovement::query()->create([...$data, 'funding_source_id' => $source->id, 'recorded_by' => $user->id]);

        return back()->with('success', $data['direction'] === 'in' ? 'Money received recorded.' : 'Payment out recorded.');
    }

    public function saveAccount(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-funding');

        $data = $request->validate([
            'bank' => ['required', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_last4' => ['required', 'digits:4'],
            'opened_on' => ['nullable', 'date', 'before_or_equal:today'],
        ], ['account_last4.digits' => 'Enter only the last four digits of the account number.']);

        ProjectBankAccount::query()->updateOrCreate(['project_id' => $project->id], $data);

        return back()->with('success', 'Project bank account saved.');
    }
}
