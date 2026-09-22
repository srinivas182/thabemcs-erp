<?php

declare(strict_types=1);

namespace App\Domains\Funding\Enums;

enum FundingStatus: string
{
    case Proposed = 'proposed';
    case Committed = 'committed';
    case Active = 'active';
    case Closed = 'closed';
}
