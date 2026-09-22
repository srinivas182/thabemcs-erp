<?php

declare(strict_types=1);

/*
| Default delegation of authority (see docs/assumptions.md DA1-DA5). Amounts are ZAR excluding VAT.
|
| Each policy is a list of bands. The first band whose 'up_to' covers the amount applies
| ('up_to' => null is the top band). 'steps' are approved in order, each by a different person
| holding that role. Nobody can approve their own request.
*/

return [

    'policies' => [

        // Site / project need raised for buying.
        'requisition' => [
            ['up_to' => null, 'steps' => ['project-manager']],
        ],

        'purchase_order' => [
            ['up_to' => 25_000, 'steps' => ['project-manager']],
            ['up_to' => 250_000, 'steps' => ['project-manager', 'finance', 'development-manager']],
            ['up_to' => 1_000_000, 'steps' => ['project-manager', 'finance', 'development-manager', 'director']],
            ['up_to' => null, 'steps' => ['project-manager', 'finance', 'director', 'director']],
        ],

        // Variation orders (absolute value of the change).
        'variation' => [
            ['up_to' => 100_000, 'steps' => ['quantity-surveyor', 'project-manager']],
            ['up_to' => 1_000_000, 'steps' => ['quantity-surveyor', 'project-manager', 'development-manager']],
            ['up_to' => null, 'steps' => ['quantity-surveyor', 'project-manager', 'development-manager', 'director']],
        ],

        // Supplier payment runs, prepared by Finance.
        'payment_run' => [
            ['up_to' => 1_000_000, 'steps' => ['director']],
            ['up_to' => null, 'steps' => ['director', 'director']],
        ],

    ],

    // Three-way match tolerance: invoice may exceed the value received by this amount (ZAR) for rounding.
    'match_tolerance' => 5.00,

    // Budget warnings are sent when committed spend reaches these percentages of the revised budget.
    'budget_alerts' => [80, 90, 100],

    // A step waiting longer than this is escalated to Directors and Company Admins.
    'escalate_after_hours' => 48,

    // Above this amount (excl. VAT) at least three quotes are required, unless a single-source reason is recorded.
    'three_quote_threshold' => 30_000,

    'vat_rate' => 0.15,

];
