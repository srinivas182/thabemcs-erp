<?php

declare(strict_types=1);

/*
| Rental settings for South African residential and commercial letting.
| Confirm with the client's rental agent or attorney (docs/assumptions.md RN1-RN8).
*/

return [
    // Rent is invoiced this many days before the month it covers.
    'bill_days_ahead' => (int) env('RENTALS_BILL_DAYS_AHEAD', 7),

    // Rent is due on this day of the month unless the lease says otherwise.
    'payment_day' => 1,

    /*
    | Notice to end a lease. The Consumer Protection Act gives a residential tenant 20 business days'
    | notice to cancel a fixed-term lease; commercial leases follow the lease itself.
    */
    'notice_days' => [
        'residential' => 20,
        'commercial' => 60,
    ],

    // Interest earned on a deposit held in an interest-bearing account (Rental Housing Act s5).
    'deposit_interest_rate' => (float) env('RENTALS_DEPOSIT_INTEREST_RATE', 5.0),

    'charge_types' => [
        'rent' => 'Rent',
        'utilities' => 'Water and electricity',
        'parking' => 'Parking',
        'levy' => 'Levy recovery',
        'other' => 'Other charge',
    ],

    'maintenance_categories' => [
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'appliance' => 'Appliance',
        'structural' => 'Structural',
        'roof' => 'Roof or gutters',
        'garden' => 'Garden and grounds',
        'security' => 'Security and access',
        'other' => 'Other',
    ],

    // Areas offered on an incoming or outgoing inspection; the list can be changed per company.
    'inspection_areas' => [
        'Walls and ceilings', 'Floors', 'Windows and doors', 'Kitchen fittings', 'Bathrooms',
        'Plumbing', 'Electrical fittings', 'Geyser', 'Garden and grounds', 'Keys and remotes',
    ],

    'conditions' => ['good', 'fair', 'poor', 'damaged'],
];
