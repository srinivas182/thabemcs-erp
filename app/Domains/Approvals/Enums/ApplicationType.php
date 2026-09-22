<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Enums;

enum ApplicationType: string
{
    case TownPlanning = 'town_planning';
    case BuildingPlans = 'building_plans';
    case EngineeringServices = 'engineering_services';
    case Environmental = 'environmental';
    case WaterUse = 'water_use';
    case Heritage = 'heritage';
    case Fire = 'fire';
    case NhbrcEnrolment = 'nhbrc_enrolment';
    case ConstructionPermit = 'construction_permit';
    case Occupancy = 'occupancy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TownPlanning => 'Town planning (rezoning, subdivision, consent)',
            self::BuildingPlans => 'Building plan approval',
            self::EngineeringServices => 'Engineering services agreement',
            self::Environmental => 'Environmental authorisation',
            self::WaterUse => 'Water use licence',
            self::Heritage => 'Heritage permit',
            self::Fire => 'Fire department approval',
            self::NhbrcEnrolment => 'NHBRC home enrolment',
            self::ConstructionPermit => 'Construction work permit / notification',
            self::Occupancy => 'Occupancy certificate',
            self::Other => 'Other',
        };
    }

    /** Usual approving body, pre-filled as a suggestion. */
    public function typicalAuthority(): string
    {
        return match ($this) {
            self::TownPlanning, self::BuildingPlans, self::EngineeringServices, self::Fire, self::Occupancy => 'Local municipality',
            self::Environmental => 'Provincial environmental department',
            self::WaterUse => 'Department of Water and Sanitation',
            self::Heritage => 'Provincial heritage resources authority',
            self::NhbrcEnrolment => 'NHBRC',
            self::ConstructionPermit => 'Department of Employment and Labour',
            self::Other => '',
        };
    }
}
