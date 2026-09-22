<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Enums;

enum FeasibilityStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
