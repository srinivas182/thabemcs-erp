<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

/**
 * The Thabekhulu development operating cycle. Every project moves through these
 * stages in order; advancing requires the stage-gate checklist to be approved.
 */
enum ProjectStage: string
{
    case Plan = 'plan';
    case Fund = 'fund';
    case Land = 'land';
    case Approve = 'approve';
    case Build = 'build';
    case SellRent = 'sell_rent';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Plan',
            self::Fund => 'Fund',
            self::Land => 'Secure land',
            self::Approve => 'Approvals',
            self::Build => 'Build',
            self::SellRent => 'Sell / rent',
            self::Close => 'Close out',
        };
    }

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }

    public function next(): ?self
    {
        $cases = self::cases();

        return $cases[$this->position()] ?? null;
    }
}
