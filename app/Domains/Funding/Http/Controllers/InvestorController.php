<?php

declare(strict_types=1);

namespace App\Domains\Funding\Http\Controllers;

use App\Domains\Funding\Enums\InvestorType;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company-wide investor register. FICA verification is recorded before money is accepted.
 */
final class InvestorController
{
    public function index(): Response
    {
        Gate::authorize('manage-funding');

        $investors = Investor::query()
            ->withSum(['fundingSources as committed_total' => fn ($q) => $q->whereIn('status', ['committed', 'active'])], 'committed_amount')
            ->withCount('fundingSources')
            ->orderBy('name')
            ->get();

        return Inertia::render('funding/investors', [
            'investors' => $investors->map(static fn (Investor $i): array => [
                'id' => $i->ulid,
                'name' => $i->name,
                'type' => $i->entity_type->value,
                'registration' => $i->maskedRegistrationNumber(),
                'contact' => collect([$i->contact_person, $i->email, $i->phone])->filter()->implode(', '),
                'ficaVerifiedAt' => $i->fica_verified_at?->toIso8601String(),
                'projects' => (int) $i->getAttribute('funding_sources_count'),
                'committed' => (float) $i->getAttribute('committed_total'),
            ]),
            'types' => array_map(static fn (InvestorType $t): string => $t->value, InvestorType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-funding');

        Investor::query()->create($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'entity_type' => ['required', Rule::enum(InvestorType::class)],
            'registration_number' => ['nullable', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('success', 'Investor added. Complete FICA verification before accepting funds.');
    }

    public function verify(Request $request, Investor $investor): RedirectResponse
    {
        Gate::authorize('manage-funding');

        /** @var User $user */
        $user = $request->user();
        $investor->forceFill(['fica_verified_at' => now(), 'fica_verified_by' => $user->id])->save();

        return back()->with('success', "{$investor->name} marked as FICA verified.");
    }

    public function destroy(Investor $investor): RedirectResponse
    {
        Gate::authorize('manage-funding');

        if (FundingSource::query()->where('investor_id', $investor->id)->exists()) {
            return back()->with('error', 'This investor is linked to project funding and cannot be removed.');
        }

        $investor->delete();

        return back()->with('success', 'Investor removed.');
    }
}
