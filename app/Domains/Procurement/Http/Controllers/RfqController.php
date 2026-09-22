<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionLine;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Procurement\Models\RfqInvitation;
use App\Domains\Procurement\Services\RfqService;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RfqController
{
    public function __construct(private readonly RfqService $rfqs, private readonly CurrentCompany $context) {}

    public function invite(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        $data = $request->validate([
            'suppliers' => ['required', 'array', 'min:1', 'max:20'],
            'suppliers.*' => ['string', Rule::exists('suppliers', 'ulid')->where('company_id', $this->context->id())],
            'closes_on' => ['required', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->rfqs->invite($requisition, array_values(Supplier::query()->whereIn('ulid', $data['suppliers'])->get()->all()), Carbon::parse((string) $data['closes_on']), $data['message'] ?? null, $user);
        } catch (ProcurementException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $result['sent'] ? 'Request for quotation emailed to '.implode(', ', $result['sent']).'.' : 'No requests were sent.';
        if ($result['skipped']) {
            $message .= ' Not sent: '.implode(', ', $result['skipped']).'.';
        }

        return back()->with($result['sent'] ? 'success' : 'error', $message);
    }

    /** Public page behind the emailed link (no sign-in). */
    public function show(string $token): Response
    {
        $invitation = $this->rfqs->find($token);
        abort_if($invitation === null, 404);

        return $this->rfqs->inCompanyOf($invitation, function () use ($invitation, $token): Response {
            if ($invitation->opened_at === null) {
                $invitation->forceFill(['opened_at' => now()])->save();
            }
            $requisition = $invitation->requisition;
            $quote = RequisitionQuote::query()->where('requisition_id', $requisition->id)->where('supplier_id', $invitation->supplier_id)->first();

            return Inertia::render('rfq/respond', [
                'token' => $token,
                'company' => $this->context->require()->name,
                'supplier' => $invitation->supplier->name,
                'reference' => $requisition->reference(),
                'title' => $requisition->title,
                'project' => $requisition->project->name,
                'neededBy' => $requisition->needed_by?->toDateString(),
                'closesOn' => $invitation->closes_on->toDateString(),
                'open' => $invitation->isOpen(),
                'declined' => $invitation->declined_at !== null,
                'message' => $invitation->message,
                // Items and quantities only: the company's own estimates are never shown to suppliers.
                'lines' => $requisition->lines()->get()->map(static fn (RequisitionLine $l): array => ['description' => $l->description, 'quantity' => (float) $l->quantity, 'unit' => $l->unit])->values(),
                'quote' => $quote ? ['amount' => (float) $quote->amount, 'reference' => $quote->reference, 'leadTime' => $quote->lead_time_days, 'validUntil' => $quote->valid_until?->toDateString(), 'notes' => $quote->notes] : null,
            ]);
        });
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->rfqs->find($token);
        abort_if($invitation === null, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'reference' => ['nullable', 'string', 'max:60'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,docx', 'max:10240'],
        ]);

        return $this->rfqs->inCompanyOf($invitation, function () use ($invitation, $data, $request): RedirectResponse {
            try {
                /** @var array{amount: float, reference?: string|null, lead_time_days?: int|null, valid_until?: string|null, notes?: string|null} $quote */
                $quote = ['amount' => (float) $data['amount'], 'reference' => $data['reference'] ?? null, 'lead_time_days' => isset($data['lead_time_days']) ? (int) $data['lead_time_days'] : null, 'valid_until' => $data['valid_until'] ?? null, 'notes' => $data['notes'] ?? null];
                $this->rfqs->submit($invitation, $quote, $request->file('file'));
            } catch (ProcurementException|QuotaExceededException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'Thank you. Your quote has been received. You can revise it here until quotes close.');
        });
    }

    public function decline(string $token): RedirectResponse
    {
        $invitation = $this->rfqs->find($token);
        abort_if($invitation === null, 404);
        $this->rfqs->inCompanyOf($invitation, fn () => $this->rfqs->decline($invitation));

        return back()->with('success', 'Thank you for letting us know.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function invitationsFor(Requisition $requisition): array
    {
        return array_values(RfqInvitation::query()->with('supplier:id,name')->where('requisition_id', $requisition->id)->orderBy('id')->get()
            ->map(static fn (RfqInvitation $i): array => [
                'supplier' => $i->supplier->name, 'email' => $i->email, 'sentAt' => $i->sent_at->toIso8601String(), 'closesOn' => $i->closes_on->toDateString(),
                'status' => $i->declined_at ? 'declined' : ($i->responded_at ? 'quoted' : ($i->opened_at ? 'opened' : 'sent')),
            ])->all());
    }
}
