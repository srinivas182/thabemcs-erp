<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

enum DevelopmentType: string
{
    case Houses = 'houses';
    case Townhouses = 'townhouses';
    case Apartments = 'apartments';
    case StudentAccommodation = 'student_accommodation';
    case Commercial = 'commercial';
    case MixedUse = 'mixed_use';
    case Industrial = 'industrial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Houses => 'Houses',
            self::Townhouses => 'Townhouses',
            self::Apartments => 'Apartments',
            self::StudentAccommodation => 'Student accommodation',
            self::Commercial => 'Commercial',
            self::MixedUse => 'Mixed-use',
            self::Industrial => 'Industrial',
            self::Other => 'Other',
        };
    }
}
