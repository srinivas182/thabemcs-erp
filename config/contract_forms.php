<?php

declare(strict_types=1);

/*
| Suggested starting values when a contract is set up on each standard form. They are typical
| values only: the actual figures come from the signed contract data, and the QS can change every
| field. Confirm these defaults with the client's QS (docs/assumptions.md CT1).
*/

return [
    'jbcc_pba' => [
        'label' => 'JBCC Principal Building Agreement', 'retention_percent' => 10, 'retention_cap_percent' => 5,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 7, 'defects_period_months' => 3,
        'notes' => 'Interim payment certificates monthly; retention reduces at practical completion and is released at final completion.',
    ],
    'jbcc_mwa' => [
        'label' => 'JBCC Minor Works Agreement', 'retention_percent' => 5, 'retention_cap_percent' => null,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 7, 'defects_period_months' => 3,
        'notes' => 'For smaller works; simpler payment and completion provisions.',
    ],
    'nec4_ecc' => [
        'label' => 'NEC4 Engineering and Construction Contract', 'retention_percent' => 5, 'retention_cap_percent' => null,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 21, 'defects_period_months' => 12,
        'notes' => 'Retention applies only if secondary Option X16 is chosen; payment follows each assessment date.',
    ],
    'gcc_2015' => [
        'label' => 'GCC 2015 (public sector)', 'retention_percent' => 10, 'retention_cap_percent' => 10,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 28, 'defects_period_months' => 12,
        'notes' => 'General Conditions of Contract for Construction Works, used on public-sector civil works.',
    ],
    'fidic' => [
        'label' => 'FIDIC', 'retention_percent' => 10, 'retention_cap_percent' => 5,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 56, 'defects_period_months' => 12,
        'notes' => 'International form; check the particular conditions for the agreed values.',
    ],
    'other' => [
        'label' => 'Own form of contract', 'retention_percent' => 10, 'retention_cap_percent' => 5,
        'release_at_practical_percent' => 50, 'payment_terms_days' => 30, 'defects_period_months' => 3,
        'notes' => 'Enter the values from the signed agreement.',
    ],
];
