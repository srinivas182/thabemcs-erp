<?php

declare(strict_types=1);

namespace App\Domains\Team\Enums;

enum FeeBasis: string
{
    case Percentage = 'percentage';
    case LumpSum = 'lump_sum';
    case TimeBased = 'time_based';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => '% of construction cost',
            self::LumpSum => 'Lump sum',
            self::TimeBased => 'Time based',
        };
    }
}
