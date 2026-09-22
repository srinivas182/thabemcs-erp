<?php

declare(strict_types=1);

namespace App\Domains\Platform\Enums;

/**
 * The nine provinces of South Africa.
 */
enum Province: string
{
    case EasternCape = 'EC';
    case FreeState = 'FS';
    case Gauteng = 'GP';
    case KwaZuluNatal = 'KZN';
    case Limpopo = 'LP';
    case Mpumalanga = 'MP';
    case NorthWest = 'NW';
    case NorthernCape = 'NC';
    case WesternCape = 'WC';

    public function label(): string
    {
        return match ($this) {
            self::EasternCape => 'Eastern Cape',
            self::FreeState => 'Free State',
            self::Gauteng => 'Gauteng',
            self::KwaZuluNatal => 'KwaZulu-Natal',
            self::Limpopo => 'Limpopo',
            self::Mpumalanga => 'Mpumalanga',
            self::NorthWest => 'North West',
            self::NorthernCape => 'Northern Cape',
            self::WesternCape => 'Western Cape',
        };
    }
}
