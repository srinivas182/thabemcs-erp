<?php

declare(strict_types=1);

namespace App\Domains\Land\Enums;

enum CheckResult: string
{
    case Pending = 'pending';
    case Clear = 'clear';
    case Issue = 'issue';
    case NotApplicable = 'not_applicable';
}
