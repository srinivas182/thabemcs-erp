<?php

declare(strict_types=1);

namespace App\Domains\Site\Services;

/**
 * Great-circle distance between two GPS points (haversine), in metres.
 */
final class Geofence
{
    private const float EARTH_RADIUS_M = 6_371_000.0;

    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
