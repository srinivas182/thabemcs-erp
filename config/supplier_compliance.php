<?php

declare(strict_types=1);

/*
| Supplier compliance rules (South Africa). See docs/assumptions.md SU1-SU4.
|
| 'documents'  what each document is and whether it expires.
| 'required'   which documents each supplier type must hold. 'block' => true means the supplier
|              cannot be appointed or paid while that document is missing or expired.
| 'cidb_limits' upper contract value (ZAR, incl. VAT) per CIDB grade. CONFIRM against the current
|              CIDB Regulations before go-live; values change when the Regulations are amended.
*/

return [

    'documents' => [
        'cipc' => ['label' => 'CIPC registration certificate', 'expires' => false],
        'tax_compliance' => ['label' => 'SARS tax compliance status (PIN)', 'expires' => true],
        'vat' => ['label' => 'VAT registration', 'expires' => false],
        'bbbee' => ['label' => 'B-BBEE certificate or sworn affidavit', 'expires' => true],
        'cidb' => ['label' => 'CIDB registration', 'expires' => true],
        'coida' => ['label' => 'COIDA letter of good standing', 'expires' => true],
        'bank_confirmation' => ['label' => 'Bank confirmation letter', 'expires' => false],
        'public_liability' => ['label' => 'Public liability insurance', 'expires' => true],
        'professional_indemnity' => ['label' => 'Professional indemnity insurance', 'expires' => true],
        'nhbrc' => ['label' => 'NHBRC home builder registration', 'expires' => true],
        'hs_file' => ['label' => 'Health and safety file', 'expires' => false],
    ],

    'required' => [
        'contractor' => [
            'cipc' => ['block' => true], 'tax_compliance' => ['block' => true], 'bbbee' => ['block' => false],
            'cidb' => ['block' => true], 'coida' => ['block' => true], 'bank_confirmation' => ['block' => true],
            'public_liability' => ['block' => true], 'hs_file' => ['block' => true],
        ],
        'subcontractor' => [
            'cipc' => ['block' => true], 'tax_compliance' => ['block' => true], 'bbbee' => ['block' => false],
            'cidb' => ['block' => false], 'coida' => ['block' => true], 'bank_confirmation' => ['block' => true],
            'hs_file' => ['block' => true],
        ],
        'supplier' => [
            'cipc' => ['block' => true], 'tax_compliance' => ['block' => true], 'bbbee' => ['block' => false],
            'bank_confirmation' => ['block' => true],
        ],
        'plant_hire' => [
            'cipc' => ['block' => true], 'tax_compliance' => ['block' => true], 'bbbee' => ['block' => false],
            'coida' => ['block' => true], 'bank_confirmation' => ['block' => true], 'public_liability' => ['block' => true],
        ],
        'consultant' => [
            'cipc' => ['block' => false], 'tax_compliance' => ['block' => true], 'bbbee' => ['block' => false],
            'bank_confirmation' => ['block' => true], 'professional_indemnity' => ['block' => true],
        ],
        'other' => [
            'tax_compliance' => ['block' => true], 'bank_confirmation' => ['block' => true],
        ],
    ],

    'alert_days' => [30, 14, 7],

    'cidb_limits' => [
        1 => 500_000,
        2 => 1_000_000,
        3 => 3_000_000,
        4 => 6_000_000,
        5 => 10_000_000,
        6 => 20_000_000,
        7 => 60_000_000,
        8 => 200_000_000,
        9 => null,
    ],

];
