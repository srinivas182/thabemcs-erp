<?php

declare(strict_types=1);

namespace App\Domains\Land\Enums;

enum LandStatus: string
{
    case Identified = 'identified';
    case UnderReview = 'under_review';
    case OfferMade = 'offer_made';
    case OfferAccepted = 'offer_accepted';
    case Transferred = 'transferred';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Identified => 'Identified',
            self::UnderReview => 'Due diligence',
            self::OfferMade => 'Offer made',
            self::OfferAccepted => 'Offer accepted',
            self::Transferred => 'Transferred',
            self::Declined => 'Declined',
        };
    }
}
