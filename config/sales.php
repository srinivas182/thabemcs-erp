<?php

declare(strict_types=1);

/*
| Sales settings for South African residential and commercial developments.
| Confirm with the client's conveyancer before go-live (docs/assumptions.md SA1-SA6).
*/

return [
    // Reservation holds the unit for this long unless the agreement is signed first.
    'reservation_days' => (int) env('SALES_RESERVATION_DAYS', 14),

    // Default days allowed for each suspensive condition, from signature.
    'condition_days' => [
        'bond_approval' => 30,
        'sale_of_property' => 60,
        'deposit' => 14,
        'other' => 30,
    ],

    'condition_types' => [
        'bond_approval' => 'Bond approval',
        'sale_of_property' => 'Sale of the buyer\'s existing property',
        'deposit' => 'Deposit paid',
        'other' => 'Other condition',
    ],

    /*
    | The transfer pipeline, in order. Dates are filled in as the conveyancer reports progress;
    | registration in the Deeds Office is what completes the sale.
    */
    'transfer_steps' => [
        'instruction' => 'Conveyancer instructed',
        'fica' => 'FICA documents received from the buyer',
        'documents_signed' => 'Transfer documents signed',
        'bond_grant' => 'Bond granted',
        'guarantees' => 'Guarantees delivered',
        'rates_clearance' => 'Rates clearance certificate',
        'transfer_duty' => 'Transfer duty or VAT paid to SARS',
        'lodgement' => 'Lodged at the Deeds Office',
        'registration' => 'Registered in the buyer\'s name',
    ],

    'unit_types' => [
        'erf' => 'Serviced erf',
        'house' => 'House',
        'sectional_unit' => 'Sectional title unit',
        'commercial' => 'Commercial unit',
    ],

    // Typical estate agency commission. The signed mandate governs.
    'commission_percent' => (float) env('SALES_COMMISSION_PERCENT', 5.0),
];
