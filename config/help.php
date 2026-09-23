<?php

declare(strict_types=1);

/*
| In-app help. Each entry matches the start of a path and gives a short explanation plus the steps
| people ask about most. Shown in the help drawer, so nobody has to leave the page to find out what to do.
*/

return [
    'dashboard' => ['title' => 'Dashboard', 'body' => 'Live projects with the ones needing attention first. Figures are refreshed as work is captured and again overnight.', 'steps' => [
        'Red means over budget, an open incident, or forecast to finish late.',
        'Amber means 80% or more of the budget used, high risks, or activities behind.',
    ]],
    'projects' => ['title' => 'Projects', 'body' => 'Every development, its stage, programme, budget and risks.', 'steps' => [
        'A project moves to the next stage only when that stage\'s checklist is complete and someone authorised approves it.',
        'The Performance tab shows whether you are ahead or behind on time and money.',
    ]],
    'requisitions' => ['title' => 'Buying things', 'body' => 'Ask for what is needed, get quotes, then raise the order.', 'steps' => [
        'Three quotes are needed above the threshold in your delegation of authority.',
        'Suppliers can quote by email through a private link; their quotes appear here.',
        'An order cannot be issued to a supplier whose compliance documents have lapsed.',
    ]],
    'finance' => ['title' => 'Invoices and payments', 'body' => 'Supplier invoices are matched against the order and what was delivered.', 'steps' => [
        'The person who captured an invoice cannot also approve it.',
        'A Director can override a failed match, with a reason, which is recorded.',
        'Payment runs leave out suppliers who are not compliant.',
    ]],
    'sales' => ['title' => 'Sales', 'body' => 'Stock, buyers, reservations, agreements and transfer.', 'steps' => [
        'Prices are captured including VAT; reports show amounts excluding VAT.',
        'A sale becomes unconditional once every condition is met or waived.',
        'Registration in the Deeds Office is what transfers the unit and releases commission.',
    ]],
    'rentals' => ['title' => 'Rentals', 'body' => 'Leases, rent, deposits, inspections and maintenance.', 'steps' => [
        'Rent for next month is invoiced automatically a week ahead, with escalations on the anniversary.',
        'Money received pays the oldest unpaid invoice first.',
        'Deposits sit in an interest-bearing account; interest is added monthly.',
    ]],
    'reports' => ['title' => 'Reports', 'body' => 'Fourteen standard reports plus any you design yourself.', 'steps' => [
        'Save the columns you want as a layout, and make it the default for everyone.',
        'Any report can be emailed weekly or monthly to the people who need it.',
    ]],
    'settings' => ['title' => 'Settings', 'body' => 'Company set-up: people, master data, imports, integrations, POPIA and API access.', 'steps' => [
        'Import opening data from a spreadsheet: it is checked first and nothing is saved unless every row is right.',
    ]],
];
