<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Enums;

/**
 * Standard development appraisal headings.
 */
enum LineCategory: string
{
    case Land = 'land';
    case Acquisition = 'acquisition';
    case ProfessionalFees = 'professional_fees';
    case Municipal = 'municipal';
    case Construction = 'construction';
    case Contingency = 'contingency';
    case Marketing = 'marketing';
    case Finance = 'finance';
    case Other = 'other';
    case Revenue = 'revenue';

    public function label(): string
    {
        return match ($this) {
            self::Land => 'Land',
            self::Acquisition => 'Acquisition costs',
            self::ProfessionalFees => 'Professional fees',
            self::Municipal => 'Municipal and statutory',
            self::Construction => 'Construction',
            self::Contingency => 'Contingency',
            self::Marketing => 'Marketing and sales',
            self::Finance => 'Finance costs',
            self::Other => 'Other costs',
            self::Revenue => 'Revenue',
        };
    }

    public function isRevenue(): bool
    {
        return $this === self::Revenue;
    }
}
