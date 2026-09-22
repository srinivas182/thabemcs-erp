<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

final class MissingCompanyContextException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No company context is set. Company-owned records can only be created inside a company context.');
    }
}
