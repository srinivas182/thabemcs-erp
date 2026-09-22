<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

/** A risk may happen; an issue is already happening. */
enum RiskKind: string
{
    case Risk = 'risk';
    case Issue = 'issue';
}
