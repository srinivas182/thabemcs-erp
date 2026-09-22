<?php

declare(strict_types=1);

/*
| Starting master data for a new company. Company Admins can change, add or deactivate entries.
| Cost code headings follow the feasibility categories (01 Land ... 09 Other).
*/

return [
    'cost_codes' => [
        ['01.01', 'Land purchase', 'land'], ['01.02', 'Transfer duty and costs', 'land'],
        ['02.01', 'Acquisition and legal', 'acquisition'],
        ['03.01', 'Architect', 'professional_fees'], ['03.02', 'Quantity surveyor', 'professional_fees'], ['03.03', 'Structural and civil engineer', 'professional_fees'],
        ['03.04', 'Electrical and mechanical engineer', 'professional_fees'], ['03.05', 'Town planner and land surveyor', 'professional_fees'], ['03.06', 'Health and safety agent', 'professional_fees'],
        ['04.01', 'Bulk contributions and municipal fees', 'municipal'], ['04.02', 'Plan submission and NHBRC enrolment', 'municipal'],
        ['05.01', 'Preliminaries and general', 'construction'], ['05.02', 'Earthworks', 'construction'], ['05.03', 'Concrete, formwork and reinforcement', 'construction'],
        ['05.04', 'Masonry', 'construction'], ['05.05', 'Roofing', 'construction'], ['05.06', 'Carpentry and joinery', 'construction'], ['05.07', 'Plumbing and drainage', 'construction'],
        ['05.08', 'Electrical installation', 'construction'], ['05.09', 'Plastering, tiling and finishes', 'construction'], ['05.10', 'Glazing and aluminium', 'construction'],
        ['05.11', 'Painting', 'construction'], ['05.12', 'External works and services', 'construction'],
        ['06.01', 'Contingency', 'contingency'], ['07.01', 'Marketing and sales commission', 'marketing'], ['08.01', 'Finance charges', 'finance'], ['09.01', 'Other costs', 'other'],
    ],
    'units' => [
        ['each', 'Each'], ['m', 'Metre'], ['m²', 'Square metre'], ['m³', 'Cubic metre'], ['kg', 'Kilogram'], ['t', 'Tonne'], ['l', 'Litre'],
        ['bag', 'Bag'], ['pack', 'Pack'], ['roll', 'Roll'], ['sheet', 'Sheet'], ['hr', 'Hour'], ['day', 'Day'], ['week', 'Week'], ['month', 'Month'], ['item', 'Item'], ['sum', 'Lump sum'],
    ],
];
