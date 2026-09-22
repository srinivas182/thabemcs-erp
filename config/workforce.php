<?php

declare(strict_types=1);

/*
| Basic Conditions of Employment Act (BCEA) defaults. See docs/assumptions.md WF1-WF5.
| Entitlements are in working days for a 5-day week and are scaled for other week lengths.
*/

return [
    'leave' => [
        'annual' => ['label' => 'Annual leave', 'days_per_year' => 15],
        'sick' => ['label' => 'Sick leave', 'days_per_cycle' => 30, 'cycle_years' => 3],
        'family' => ['label' => 'Family responsibility leave', 'days_per_year' => 3],
        'maternity' => ['label' => 'Maternity leave', 'note' => 'Four consecutive months'],
        'parental' => ['label' => 'Parental leave', 'note' => 'Ten consecutive days'],
        'unpaid' => ['label' => 'Unpaid leave'],
    ],
    'overtime_max_hours_per_week' => 10,
    'overtime_max_hours_per_day' => 3,
];
