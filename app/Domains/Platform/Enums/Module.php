<?php

declare(strict_types=1);

namespace App\Domains\Platform\Enums;

/**
 * Business modules that the Super Admin can enable or disable per company.
 */
enum Module: string
{
    case Projects = 'projects';
    case Feasibility = 'feasibility';
    case Funding = 'funding';
    case Land = 'land';
    case Approvals = 'approvals';
    case Suppliers = 'suppliers';
    case Procurement = 'procurement';
    case Finance = 'finance';
    case Site = 'site';
    case Safety = 'safety';
    case Workforce = 'workforce';
    case Plant = 'plant';
    case Documents = 'documents';
    case Reporting = 'reporting';
    case Sales = 'sales';
    case Rentals = 'rentals';

    public function label(): string
    {
        return match ($this) {
            self::Projects => 'Projects',
            self::Feasibility => 'Feasibility',
            self::Funding => 'Funding & Investors',
            self::Land => 'Land & Due Diligence',
            self::Approvals => 'Statutory Approvals',
            self::Suppliers => 'Contractors & Suppliers',
            self::Procurement => 'Procurement',
            self::Finance => 'Finance',
            self::Site => 'Site Management',
            self::Safety => 'Health & Safety',
            self::Workforce => 'Workforce',
            self::Plant => 'Plant & Equipment',
            self::Documents => 'Documents',
            self::Reporting => 'Reports',
            self::Sales => 'Sales',
            self::Rentals => 'Rentals',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::cases());
    }
}
