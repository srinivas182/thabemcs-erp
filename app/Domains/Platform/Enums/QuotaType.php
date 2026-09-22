<?php

declare(strict_types=1);

namespace App\Domains\Platform\Enums;

/**
 * Limits the Super Admin sets for each company.
 */
enum QuotaType: string
{
    case Projects = 'projects';
    case Users = 'users';

    /**
     * Column on the companies table that holds the limit (null = unlimited).
     */
    public function column(): string
    {
        return 'max_'.$this->value;
    }
}
