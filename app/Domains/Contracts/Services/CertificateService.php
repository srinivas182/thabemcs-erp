<?php

declare(strict_types=1);

namespace App\Domains\Contracts\Services;

use App\Domains\Contracts\Models\Contract;
use App\Domains\Contracts\Models\PaymentCertificate;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Interim payment certificates with retention.
 *
 * Retention is withheld at the contract rate on the value to date, stops growing at the cap (if set),
 * is partly released at practical completion and fully released at final completion.
 */
final class CertificateService
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @return array{gross_value: float, retention_held: float, retention_released: float, previous_certified: float, amount_due: float, vat: float}
     */
    public function calculate(Contract $contract, float $grossValue, Carbon $valuationDate, ?int $ignoreCertificateId = null): array
    {
        if ($grossValue < 0) {
            throw new InvalidArgumentException('The value to date cannot be negative.');
        }

        $held = $grossValue * (float) $contract->retention_percent / 100;
        if ($contract->retention_cap_percent !== null) {
            $held = min($held, (float) $contract->contract_sum * (float) $contract->retention_cap_percent / 100);
        }
        $held = round($held, 2);

        $released = 0.0;
        if ($contract->final_completion_on !== null && $valuationDate->greaterThanOrEqualTo($contract->final_completion_on)) {
            $released = $held;
        } elseif ($contract->practical_completion_on !== null && $valuationDate->greaterThanOrEqualTo($contract->practical_completion_on)) {
            $released = round($held * (float) $contract->release_at_practical_percent / 100, 2);
        }

        $previous = (float) PaymentCertificate::query()->where('contract_id', $contract->id)
            ->whereIn('status', ['certified', 'pending_approval', 'draft'])
            ->when($ignoreCertificateId, fn ($q) => $q->whereKeyNot($ignoreCertificateId))
            ->sum('amount_due');

        $due = round($grossValue - ($held - $released) - $previous, 2);
        $vatRate = $contract->supplier->vat_number !== null ? (float) config('delegation_of_authority.vat_rate') : 0.0;

        return [
            'gross_value' => round($grossValue, 2), 'retention_held' => $held, 'retention_released' => $released,
            'previous_certified' => round($previous, 2), 'amount_due' => $due, 'vat' => round($due * $vatRate, 2),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function prepare(Contract $contract, float $grossValue, Carbon $valuationDate, ?string $notes, User $by): PaymentCertificate
    {
        $open = PaymentCertificate::query()->where('contract_id', $contract->id)->whereIn('status', ['draft', 'pending_approval'])->exists();
        if ($open) {
            throw new InvalidArgumentException('Finish the certificate already in progress before preparing the next one.');
        }

        $last = PaymentCertificate::query()->where('contract_id', $contract->id)->where('status', 'certified')->orderByDesc('number')->first();
        if ($last !== null && $grossValue < (float) $last->gross_value) {
            throw new InvalidArgumentException('The value to date is lower than the last certificate. Record a negative variation instead of reducing the valuation.');
        }

        return DB::transaction(fn (): PaymentCertificate => PaymentCertificate::query()->create([
            ...$this->calculate($contract, $grossValue, $valuationDate),
            'contract_id' => $contract->id,
            'number' => (int) PaymentCertificate::query()->where('contract_id', $contract->id)->lockForUpdate()->max('number') + 1,
            'valuation_date' => $valuationDate->toDateString(),
            'status' => 'draft',
            'notes' => $notes,
            'prepared_by' => $by->id,
        ]));
    }

    /**
     * @throws ApprovalException
     */
    public function submit(PaymentCertificate $certificate, User $by): void
    {
        if ($certificate->status !== 'draft') {
            throw new ApprovalException('Only draft certificates can be submitted.');
        }
        DB::transaction(function () use ($certificate, $by): void {
            $certificate->forceFill(['status' => 'pending_approval'])->save();
            $this->approvals->submit($certificate, 'payment_certificate', max(0.0, (float) $certificate->amount_due), $by);
        });
    }

    /**
     * Totals for the contract register.
     *
     * @return array{certified: float, retentionHeld: float, retentionReleased: float, remaining: float}
     */
    public function position(Contract $contract): array
    {
        $last = PaymentCertificate::query()->where('contract_id', $contract->id)->where('status', 'certified')->orderByDesc('number')->first();
        $gross = $last ? (float) $last->gross_value : 0.0;

        return [
            'certified' => $gross,
            'retentionHeld' => $last ? (float) $last->retention_held : 0.0,
            'retentionReleased' => $last ? (float) $last->retention_released : 0.0,
            'remaining' => round((float) $contract->contract_sum - $gross, 2),
        ];
    }
}
