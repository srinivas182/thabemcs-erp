<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Services;

use App\Domains\Suppliers\Enums\ComplianceState;
use App\Domains\Suppliers\Exceptions\SupplierNotCompliantException;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Models\SupplierDocument;
use Illuminate\Support\Carbon;

/**
 * Works out whether a supplier holds every document its type requires, and whether it
 * may be appointed or paid. Rules live in config/supplier_compliance.php.
 */
final class ComplianceService
{
    /**
     * @return list<array{type: string, label: string, block: bool, state: ComplianceState, reference: string|null, expiresOn: string|null, daysLeft: int|null, verified: bool, documentId: string|null}>
     */
    public function evaluate(Supplier $supplier): array
    {
        /** @var array<string, array{label: string, expires: bool}> $definitions */
        $definitions = config('supplier_compliance.documents');
        /** @var array<string, array{block: bool}> $required */
        $required = config("supplier_compliance.required.{$supplier->type->value}", []);
        $warnDays = (int) max(config('supplier_compliance.alert_days', [30]));

        // Latest record per document type.
        $held = $supplier->complianceDocuments()->with('document:id,ulid')->orderByDesc('id')->get()
            ->unique('type')->keyBy('type');

        $rows = [];
        foreach ($required as $type => $rule) {
            /** @var SupplierDocument|null $doc */
            $doc = $held->get($type);
            $expires = $definitions[$type]['expires'] ?? false;
            $daysLeft = $doc?->expires_on ? (int) Carbon::today()->diffInDays($doc->expires_on, false) : null;

            $state = match (true) {
                $doc === null => ComplianceState::Missing,
                $expires && $doc->expires_on === null => ComplianceState::Missing,
                $daysLeft !== null && $daysLeft < 0 => ComplianceState::Expired,
                $daysLeft !== null && $daysLeft <= $warnDays => ComplianceState::Expiring,
                default => ComplianceState::Valid,
            };

            $rows[] = [
                'type' => $type,
                'label' => $definitions[$type]['label'] ?? $type,
                'block' => $rule['block'],
                'state' => $state,
                'reference' => $doc?->reference,
                'expiresOn' => $doc?->expires_on?->toDateString(),
                'daysLeft' => $daysLeft,
                'verified' => $doc?->verified_at !== null,
                'documentId' => $doc?->document?->ulid,
            ];
        }

        return $rows;
    }

    /**
     * Reasons the supplier may not be appointed or paid (empty when compliant).
     *
     * @return list<string>
     */
    public function blockers(Supplier $supplier, ?float $contractValue = null): array
    {
        $reasons = [];

        if ($supplier->status !== 'active') {
            $reasons[] = 'supplier is suspended';
        }

        foreach ($this->evaluate($supplier) as $row) {
            if ($row['block'] && in_array($row['state'], [ComplianceState::Missing, ComplianceState::Expired], true)) {
                $reasons[] = "{$row['label']} is ".($row['state'] === ComplianceState::Missing ? 'missing' : 'expired');
            }
        }

        if ($contractValue !== null && ($cidb = $this->cidbProblem($supplier, $contractValue)) !== null) {
            $reasons[] = $cidb;
        }

        return $reasons;
    }

    public function isCompliant(Supplier $supplier): bool
    {
        return $this->blockers($supplier) === [];
    }

    /**
     * @throws SupplierNotCompliantException
     */
    public function ensureCanTransact(Supplier $supplier, ?float $contractValue = null): void
    {
        $reasons = $this->blockers($supplier, $contractValue);

        if ($reasons !== []) {
            throw new SupplierNotCompliantException($reasons);
        }
    }

    /**
     * Highest contract value (incl. VAT) the supplier's CIDB grade allows; null = no limit.
     */
    public function cidbLimit(?int $grade): ?int
    {
        if ($grade === null) {
            return 0;
        }

        /** @var array<int, int|null> $limits */
        $limits = config('supplier_compliance.cidb_limits');

        return $limits[$grade] ?? 0;
    }

    /**
     * Only contractors and subcontractors are graded by the CIDB.
     */
    public function cidbProblem(Supplier $supplier, float $contractValue): ?string
    {
        if (! in_array($supplier->type->value, ['contractor', 'subcontractor'], true)) {
            return null;
        }

        $limit = $this->cidbLimit($supplier->cidb_grade);

        if ($limit === null || $contractValue <= $limit) {
            return null;
        }

        return $supplier->cidb_grade === null
            ? 'no CIDB grading recorded'
            : sprintf('CIDB grade %d allows contracts up to R%s', $supplier->cidb_grade, number_format($limit, 0, '.', ' '));
    }
}
