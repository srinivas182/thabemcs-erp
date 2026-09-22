<?php

declare(strict_types=1);

namespace App\Domains\Team\Enums;

/**
 * Professional disciplines on a development, with the South African statutory council
 * each must be registered with.
 */
enum Discipline: string
{
    case Architect = 'architect';
    case QuantitySurveyor = 'quantity_surveyor';
    case CivilEngineer = 'civil_engineer';
    case StructuralEngineer = 'structural_engineer';
    case ElectricalEngineer = 'electrical_engineer';
    case MechanicalEngineer = 'mechanical_engineer';
    case TownPlanner = 'town_planner';
    case LandSurveyor = 'land_surveyor';
    case ProjectManager = 'project_manager';
    case HealthSafetyAgent = 'health_safety_agent';
    case EnvironmentalPractitioner = 'environmental_practitioner';
    case Attorney = 'attorney';
    case Conveyancer = 'conveyancer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Architect => 'Architect',
            self::QuantitySurveyor => 'Quantity surveyor',
            self::CivilEngineer => 'Civil engineer',
            self::StructuralEngineer => 'Structural engineer',
            self::ElectricalEngineer => 'Electrical engineer',
            self::MechanicalEngineer => 'Mechanical engineer',
            self::TownPlanner => 'Town planner',
            self::LandSurveyor => 'Land surveyor',
            self::ProjectManager => 'Construction project manager',
            self::HealthSafetyAgent => 'Health and safety agent',
            self::EnvironmentalPractitioner => 'Environmental assessment practitioner',
            self::Attorney => 'Property attorney',
            self::Conveyancer => 'Conveyancer',
            self::Other => 'Other',
        };
    }

    public function registrationBody(): ?string
    {
        return match ($this) {
            self::Architect => 'SACAP',
            self::QuantitySurveyor => 'SACQSP',
            self::CivilEngineer, self::StructuralEngineer, self::ElectricalEngineer, self::MechanicalEngineer => 'ECSA',
            self::TownPlanner => 'SACPLAN',
            self::LandSurveyor => 'SAGC',
            self::ProjectManager, self::HealthSafetyAgent => 'SACPCMP',
            self::EnvironmentalPractitioner => 'EAPASA',
            self::Attorney, self::Conveyancer => 'LPC',
            self::Other => null,
        };
    }
}
