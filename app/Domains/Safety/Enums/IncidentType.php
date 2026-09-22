<?php

declare(strict_types=1);

namespace App\Domains\Safety\Enums;

enum IncidentType: string
{
    case NearMiss = 'near_miss';
    case FirstAid = 'first_aid';
    case Medical = 'medical';
    case LostTime = 'lost_time';
    case DangerousOccurrence = 'dangerous_occurrence';
    case PropertyDamage = 'property_damage';
    case Fatality = 'fatality';

    public function label(): string
    {
        return match ($this) {
            self::NearMiss => 'Near miss',
            self::FirstAid => 'First aid case',
            self::Medical => 'Medical treatment case',
            self::LostTime => 'Lost time injury',
            self::DangerousOccurrence => 'Dangerous occurrence',
            self::PropertyDamage => 'Property or equipment damage',
            self::Fatality => 'Fatality',
        };
    }

    /**
     * Types that must be reported to the Department of Employment and Labour under section 24
     * of the OHS Act. Lost-time injuries are reportable depending on severity, so they are flagged for review.
     */
    public function defaultReportable(): bool
    {
        return in_array($this, [self::Fatality, self::DangerousOccurrence], true);
    }

    public function needsReportabilityReview(): bool
    {
        return in_array($this, [self::LostTime, self::Medical], true);
    }
}
