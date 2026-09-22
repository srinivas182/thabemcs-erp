<?php

declare(strict_types=1);

namespace App\Domains\Documents\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Drawing = 'drawing';
    case Approval = 'approval';
    case Report = 'report';
    case Certificate = 'certificate';
    case Compliance = 'compliance';
    case Financial = 'financial';
    case Correspondence = 'correspondence';
    case Minutes = 'minutes';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contract or agreement',
            self::Drawing => 'Drawing',
            self::Approval => 'Approval or permit',
            self::Report => 'Report',
            self::Certificate => 'Certificate',
            self::Compliance => 'Compliance document',
            self::Financial => 'Financial (quote, invoice, statement)',
            self::Correspondence => 'Correspondence',
            self::Minutes => 'Meeting minutes',
            self::Photo => 'Photo',
            self::Other => 'Other',
        };
    }
}
