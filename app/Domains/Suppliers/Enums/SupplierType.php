<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Enums;

enum SupplierType: string
{
    case Contractor = 'contractor';
    case Subcontractor = 'subcontractor';
    case Supplier = 'supplier';
    case PlantHire = 'plant_hire';
    case Consultant = 'consultant';
    case EstateAgency = 'estate_agency';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contractor => 'Main contractor',
            self::Subcontractor => 'Subcontractor',
            self::Supplier => 'Materials supplier',
            self::PlantHire => 'Plant hire',
            self::Consultant => 'Consultant',
            self::EstateAgency => 'Estate agency',
            self::Other => 'Other service provider',
        };
    }
}
