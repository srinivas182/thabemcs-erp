<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Domains\Platform\Enums\QuotaType;
use RuntimeException;

final class QuotaExceededException extends RuntimeException
{
    public function __construct(public readonly QuotaType $quota, public readonly int $limit)
    {
        parent::__construct($quota === QuotaType::StorageMb
            ? sprintf('This company has used its %d MB of document storage. Ask the Super Admin to raise the limit.', $limit)
            : sprintf('This company has reached its limit of %d %s. Ask the Super Admin to raise the limit.', $limit, $quota->value));
    }
}
