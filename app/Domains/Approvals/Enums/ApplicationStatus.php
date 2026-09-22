<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Enums;

enum ApplicationStatus: string
{
    case Preparing = 'preparing';
    case Submitted = 'submitted';
    case Query = 'query';
    case Approved = 'approved';
    case Refused = 'refused';
    case Lapsed = 'lapsed';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparing',
            self::Submitted => 'Submitted',
            self::Query => 'Query from authority',
            self::Approved => 'Approved',
            self::Refused => 'Refused',
            self::Lapsed => 'Lapsed',
        };
    }
}
