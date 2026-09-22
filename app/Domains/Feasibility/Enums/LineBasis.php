<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Enums;

/**
 * How a line's value is worked out.
 */
enum LineBasis: string
{
    case Amount = 'amount';
    case PercentOfConstruction = 'percent_of_construction';
    case PercentOfRevenue = 'percent_of_revenue';

    public function label(): string
    {
        return match ($this) {
            self::Amount => 'Amount',
            self::PercentOfConstruction => '% of construction',
            self::PercentOfRevenue => '% of revenue',
        };
    }
}
