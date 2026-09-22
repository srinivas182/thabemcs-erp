<?php

declare(strict_types=1);

namespace App\Domains\Site\Enums;

/** Must match WEATHER in packages/shared/src/site-diary.ts. */
enum Weather: string
{
    case Clear = 'clear';
    case Cloudy = 'cloudy';
    case Rain = 'rain';
    case Storm = 'storm';
    case Wind = 'wind';
    case Heat = 'heat';

    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Clear',
            self::Cloudy => 'Cloudy',
            self::Rain => 'Rain',
            self::Storm => 'Storm',
            self::Wind => 'Windy',
            self::Heat => 'Very hot',
        };
    }
}
