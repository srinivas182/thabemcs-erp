<?php

declare(strict_types=1);

/*
| Closing a development out. The list follows normal South African practice for a residential or
| mixed-use development; confirm with the client's QS and attorney (docs/assumptions.md CL1).
*/

return [
    'items' => [
        'construction' => [
            'practical_completion' => 'Certificate of practical completion issued',
            'works_completion' => 'Certificate of works completion issued',
            'final_completion' => 'Certificate of final completion issued',
            'snags_closed' => 'All snags closed and signed off',
            'retention_released' => 'Retention released to the contractor',
            'final_account' => 'Final account agreed with the contractor',
        ],
        'statutory' => [
            'occupancy_certificate' => 'Occupancy certificate from the municipality',
            'electrical_coc' => 'Electrical certificate of compliance',
            'plumbing_coc' => 'Plumbing certificate of compliance',
            'gas_coc' => 'Gas certificate of compliance (where gas is installed)',
            'nhbrc_completion' => 'NHBRC enrolment closed for every home',
            'fire_approval' => 'Fire department sign-off',
        ],
        'handover' => [
            'as_built_drawings' => 'As-built drawings handed over',
            'operating_manuals' => 'Operating manuals and warranties handed over',
            'keys_handover' => 'Keys, remotes and access cards handed over',
            'body_corporate' => 'Handover to the body corporate or home owners association',
            'municipal_services' => 'Municipal services and meters transferred',
            'insurance' => 'Contract works insurance ended and building insurance started',
        ],
        'financial' => [
            'supplier_accounts' => 'All supplier accounts settled',
            'project_account' => 'Final project account signed off',
            'funding_settled' => 'Funding facilities settled or released',
            'distributions' => 'Investor distributions paid',
        ],
        'records' => [
            'safety_file' => 'Health and safety file closed and archived',
            'document_archive' => 'Project records archived with retention dates set',
        ],
    ],

    'optional' => ['gas_coc', 'fire_approval', 'body_corporate'],
];
