<?php

declare(strict_types=1);

use App\Domains\Site\Services\Geofence;

it('measures distance between GPS points in metres', function (): void {
    $g = new Geofence;

    expect($g->distance(-29.5389, 31.2139, -29.5389, 31.2139))->toBe(0)
        // One degree of latitude is about 111 km.
        ->and($g->distance(-29.0, 31.0, -30.0, 31.0))->toBeGreaterThan(110_000)->toBeLessThan(112_000);
});
