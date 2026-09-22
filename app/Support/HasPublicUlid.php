<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Numeric primary key internally, ULID in URLs and APIs (never expose sequential ids).
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
