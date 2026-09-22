<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Exceptions;

use RuntimeException;

/**
 * Thrown when a supplier may not be appointed or paid. Procurement and payments call
 * ComplianceService::ensureCanTransact() before committing money.
 */
final class SupplierNotCompliantException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('This supplier is not compliant: '.implode('; ', $reasons).'.');
    }
}
