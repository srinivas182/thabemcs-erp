<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

enum RiskStatus: string
{
    case Open = 'open';
    case Monitoring = 'monitoring';
    case Closed = 'closed';
}
