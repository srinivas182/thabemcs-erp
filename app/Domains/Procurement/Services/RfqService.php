<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Mail\RfqInvitationMail;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Procurement\Models\RfqInvitation;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Requests for quotation sent by email. Each supplier gets a private link (a random token of which only
 * the SHA-256 hash is stored) where they can see the items and submit or revise their quote until closing.
 */
final class RfqService
{
    public function __construct(private readonly DocumentService $documents, private readonly CurrentCompany $context) {}

    /**
     * @param  list<Supplier>  $suppliers
     * @return array{sent: list<string>, skipped: list<string>}
     */
    public function invite(Requisition $requisition, array $suppliers, Carbon $closesOn, ?string $message, User $by): array
    {
        if ($requisition->status !== 'approved') {
            throw new ProcurementException('Quotes can only be requested once the requisition is approved.');
        }

        $sent = [];
        $skipped = [];
        $company = $this->context->require();

        foreach ($suppliers as $supplier) {
            if ($supplier->email === null || $supplier->email === '') {
                $skipped[] = "{$supplier->name} (no email address)";

                continue;
            }
            if (RfqInvitation::query()->where('requisition_id', $requisition->id)->where('supplier_id', $supplier->id)->exists()) {
                $skipped[] = "{$supplier->name} (already invited)";

                continue;
            }

            $token = Str::random(48);
            DB::transaction(fn () => RfqInvitation::query()->create([
                'requisition_id' => $requisition->id, 'supplier_id' => $supplier->id, 'email' => $supplier->email,
                'token_hash' => hash('sha256', $token), 'closes_on' => $closesOn->toDateString(), 'message' => $message,
                'sent_by' => $by->id, 'sent_at' => now(),
            ]));

            Mail::to($supplier->email)->send(new RfqInvitationMail(
                $company->name, $requisition->reference(), $requisition->title, $closesOn->format('j F Y'),
                route('rfq.respond', $token), $message, $by->name,
            ));
            $sent[] = $supplier->name;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Resolve a link token. Runs before any company is selected, so it bypasses the company scope.
     */
    public function find(string $token): ?RfqInvitation
    {
        return RfqInvitation::query()->withoutGlobalScope(CompanyScope::class)->where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function inCompanyOf(RfqInvitation $invitation, callable $callback): mixed
    {
        $company = Company::query()->findOrFail($invitation->getAttribute('company_id'));

        return $this->context->runFor($company, $callback);
    }

    /**
     * @param  array{amount: float, reference?: string|null, lead_time_days?: int|null, valid_until?: string|null, notes?: string|null}  $data
     */
    public function submit(RfqInvitation $invitation, array $data, ?UploadedFile $file): RequisitionQuote
    {
        if (! $invitation->isOpen()) {
            throw new ProcurementException('Quotes for this request have closed.');
        }

        return DB::transaction(function () use ($invitation, $data, $file): RequisitionQuote {
            $requisition = $invitation->requisition;
            $documentId = null;
            if ($file !== null) {
                // Stored as if uploaded by the buyer who sent the request; the audit log records the supplier portal.
                $documentId = $this->documents->upload($file, [
                    'project_id' => $requisition->project_id, 'folder' => 'Procurement/Quotes',
                    'title' => "Quote {$invitation->supplier->name} for {$requisition->reference()}", 'category' => DocumentCategory::Financial,
                    'restricted_to_roles' => [Role::Procurement->value, Role::Finance->value, Role::DevelopmentManager->value],
                ], $invitation->sender)->id;
            }

            $quote = RequisitionQuote::query()->updateOrCreate(
                ['requisition_id' => $requisition->id, 'supplier_id' => $invitation->supplier_id],
                [
                    'amount' => $data['amount'], 'reference' => $data['reference'] ?? null, 'lead_time_days' => $data['lead_time_days'] ?? null,
                    'valid_until' => $data['valid_until'] ?? null, 'notes' => $data['notes'] ?? null, 'submitted_by_supplier' => true,
                    ...($documentId !== null ? ['document_id' => $documentId] : []),
                ],
            );

            $invitation->forceFill(['responded_at' => now()])->save();
            activity('procurement')->performedOn($requisition)->withProperties(['supplier' => $invitation->supplier->name, 'amount' => $data['amount']])->log('Quote submitted through supplier link');

            $invitation->sender->notify(new SystemMessage(
                "Quote received: {$invitation->supplier->name}",
                "{$requisition->reference()} {$requisition->title}: R".number_format((float) $data['amount'], 2, '.', ' ').' excl. VAT.',
                route('requisitions.show', $requisition),
            ));

            return $quote;
        });
    }

    public function decline(RfqInvitation $invitation): void
    {
        $invitation->forceFill(['declined_at' => now()])->save();
    }
}
