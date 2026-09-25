<?php

declare(strict_types=1);

namespace App\Domains\Platform\Enums;

/**
 * Default company roles. Company Admins can create additional roles later.
 */
enum Role: string
{
    case CompanyAdmin = 'company-admin';
    case Director = 'director';
    case DevelopmentManager = 'development-manager';
    case ProjectManager = 'project-manager';
    case QuantitySurveyor = 'quantity-surveyor';
    case SiteManager = 'site-manager';
    case SafetyOfficer = 'safety-officer';
    case Procurement = 'procurement';
    case Finance = 'finance';
    case SalesAndLeasing = 'sales-leasing';
    case Marketing = 'marketing';
    case Contractor = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAdmin => 'Company Administrator',
            self::Director => 'Director / Executive',
            self::DevelopmentManager => 'Development Manager',
            self::ProjectManager => 'Project Manager',
            self::QuantitySurveyor => 'Quantity Surveyor',
            self::SiteManager => 'Site Manager',
            self::SafetyOfficer => 'Health & Safety Officer',
            self::Procurement => 'Procurement',
            self::Finance => 'Finance',
            self::SalesAndLeasing => 'Sales & Leasing',
            self::Marketing => 'Marketing',
            self::Contractor => 'Contractor (external)',
        };
    }
}
