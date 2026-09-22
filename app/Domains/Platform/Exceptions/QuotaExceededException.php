<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Domains\Platform\Enums\QuotaType;
use RuntimeException;

final class QuotaExceededException extends RuntimeException
{
    public function __construct(public readonly QuotaType $quota, public readonly int $limit)
    {
        parent::__construct(sprintf(
            'This company has reached its limit of %d %s. Ask the Super Admin to raise the limit.',
            $limit,
            $quota->value,
        ));
    }
}
