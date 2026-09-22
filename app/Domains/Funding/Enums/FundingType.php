<?php

declare(strict_types=1);

namespace App\Domains\Funding\Enums;

enum FundingType: string
{
    case Equity = 'equity';
    case Investor = 'investor';
    case Debt = 'debt';
    case Grant = 'grant';

    public function label(): string
    {
        return match ($this) {
            self::Equity => 'Developer equity',
            self::Investor => 'Investor capital',
            self::Debt => 'Debt (loan)',
            self::Grant => 'Grant or subsidy',
        };
    }
}
