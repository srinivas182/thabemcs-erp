<?php

declare(strict_types=1);

namespace App\Domains\Team\Enums;

enum ClaimStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
