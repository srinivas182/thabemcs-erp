<?php

declare(strict_types=1);

namespace App\Domains\Platform\Enums;

enum CompanyStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
