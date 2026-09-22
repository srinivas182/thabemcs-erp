<?php

declare(strict_types=1);

namespace App\Domains\Funding\Enums;

enum InvestorType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Trust = 'trust';
    case Fund = 'fund';
}
