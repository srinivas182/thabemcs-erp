<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Enums;

enum ComplianceState: string
{
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Missing = 'missing';
}
