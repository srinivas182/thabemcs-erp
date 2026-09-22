<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
