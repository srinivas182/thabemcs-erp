<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Team\Enums\ClaimStatus;
use App\Domains\Team\Enums\Discipline;
use App\Domains\Team\Enums\FeeBasis;
use App\Domains\Team\Models\FeeClaim;
use App\Domains\Team\Models\ProfessionalAppointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The professional team on a project: appointments, council registration checks and fee claims.
 */
final class TeamController
{
    public function show(Request $request, Project $project): Response
    {
        /** @var User $user */
        $user = $request->user();

        $appointments = ProfessionalAppointment::query()->with('claims')->where('project_id', $project->id)->orderBy('discipline')->get();

        return Inertia::render('projects/team', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'appointments' => $appointments->map(static function (ProfessionalAppointment $a): array {
                $claimed = (float) $a->claims->whereIn('status', [ClaimStatus::Approved, ClaimStatus::Paid])->sum('amount');

                return [
                    'id' => $a->ulid,
                    'discipline' => $a->discipline->label(),
                    'firm' => $a->firm_name,
                    'contact' => collect([$a->contact_name, $a->email, $a->phone])->filter()->implode(', '),
                    'registrationBody' => $a->registration_body,
                    'registrationNumber' => $a->registration_number,
                    'verifiedAt' => $a->registration_verified_at?->toIso8601String(),
                    'feeBasis' => $a->fee_basis->label(),
                    'feePercentage' => $a->fee_percentage,
                    'agreedFee' => $a->agreed_fee === null ? null : (float) $a->agreed_fee,
                    'approvedClaims' => $claimed,
                    'overClaimed' => $a->agreed_fee !== null && $claimed > (float) $a->agreed_fee,
                    'appointedOn' => $a->appointed_on?->toDateString(),
                    'status' => $a->status,
                    'claims' => $a->claims->map(static fn (FeeClaim $c): array => [
                        'id' => $c->id, 'number' => $c->claim_number, 'description' => $c->description, 'amount' => (float) $c->amount,
                        'submittedOn' => $c->submitted_on->toDateString(), 'status' => $c->status->value, 'paidOn' => $c->paid_on?->toDateString(),
                    ])->values(),
                ];
            }),
            'disciplines' => array_map(static fn (Discipline $d): array => ['key' => $d->value, 'label' => $d->label(), 'body' => $d->registrationBody()], Discipline::cases()),
            'feeBases' => array_map(static fn (FeeBasis $b): array => ['key' => $b->value, 'label' => $b->label()], FeeBasis::cases()),
            'can' => [
                'manage' => $user->can('manage-team'),
                'approveClaims' => $user->can('approve-fee-claims'),
                'markPaid' => $user->can('manage-funding'),
            ],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-team');

        $data = $request->validate([
            'discipline' => ['required', Rule::enum(Discipline::class)],
            'firm_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'fee_basis' => ['required', Rule::enum(FeeBasis::class)],
            'fee_percentage' => ['nullable', 'required_if:fee_basis,percentage', 'numeric', 'between:0,30'],
            'agreed_fee' => ['nullable', 'numeric', 'min:0'],
            'appointed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $discipline = Discipline::from((string) $data['discipline']);

        ProfessionalAppointment::query()->create([
            ...$data,
            'project_id' => $project->id,
            'registration_body' => $discipline->registrationBody(),
            'status' => isset($data['appointed_on']) ? 'appointed' : 'proposed',
        ]);

        return back()->with('success', "{$discipline->label()} added to the team.");
    }

    public function verify(Request $request, ProfessionalAppointment $appointment): RedirectResponse
    {
        Gate::authorize('manage-team');

        if ($appointment->registration_number === null) {
            return back()->with('error', 'Add the registration number before verifying it.');
        }

        /** @var User $user */
        $user = $request->user();
        $appointment->forceFill(['registration_verified_at' => now(), 'registration_verified_by' => $user->id])->save();

        return back()->with('success', "{$appointment->registration_body} registration verified for {$appointment->firm_name}.");
    }

    public function storeClaim(Request $request, ProfessionalAppointment $appointment): RedirectResponse
    {
        Gate::authorize('manage-team');

        $data = $request->validate([
            'claim_number' => ['required', 'string', 'max:40', Rule::unique('fee_claims')->where('professional_appointment_id', $appointment->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'submitted_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        FeeClaim::query()->create([...$data, 'professional_appointment_id' => $appointment->id]);

        return back()->with('success', 'Fee claim recorded.');
    }

    public function updateClaim(Request $request, FeeClaim $claim): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ClaimStatus::class)]]);
        $status = ClaimStatus::from((string) $data['status']);

        /** @var User $user */
        $user = $request->user();

        match ($status) {
            ClaimStatus::Approved, ClaimStatus::Rejected => Gate::authorize('approve-fee-claims'),
            ClaimStatus::Paid => Gate::authorize('manage-funding'),
            ClaimStatus::Submitted => Gate::authorize('manage-team'),
        };

        if ($status === ClaimStatus::Paid && $claim->status !== ClaimStatus::Approved) {
            return back()->with('error', 'Only approved claims can be marked as paid.');
        }

        $claim->forceFill([
            'status' => $status,
            'approved_by' => in_array($status, [ClaimStatus::Approved, ClaimStatus::Rejected], true) ? $user->id : $claim->approved_by,
            'approved_at' => in_array($status, [ClaimStatus::Approved, ClaimStatus::Rejected], true) ? now() : $claim->approved_at,
            'paid_on' => $status === ClaimStatus::Paid ? now()->toDateString() : $claim->paid_on,
        ])->save();

        return back()->with('success', 'Claim '.$claim->claim_number.' '.$status->value.'.');
    }
}
