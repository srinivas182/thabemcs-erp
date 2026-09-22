<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'To do',
            self::InProgress => 'In progress',
            self::Done => 'Done',
        };
    }
}
