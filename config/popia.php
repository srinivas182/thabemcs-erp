<?php

declare(strict_types=1);

/*
| POPIA tooling (Protection of Personal Information Act 4 of 2013). See docs/assumptions.md PO1-PO5.
|
| 'register': the personal information this system holds, why, and who can see it (the record of
|             processing shown to the Information Officer).
| 'retention': records the monthly clean-up removes or anonymises, with the default months to keep.
|             Minimums reflect other laws (BCEA s31: employment records 3 years). Confirm with the
|             client's Information Officer before switching the clean-up on.
*/

return [
    'information_officer' => [
        'name' => env('POPIA_INFORMATION_OFFICER'),
        'email' => env('POPIA_INFORMATION_OFFICER_EMAIL'),
    ],

    'request_days' => 30,

    'register' => [
        ['category' => 'System users', 'data' => 'Name, email, phone, job title, sign-in history', 'purpose' => 'Access to the system and audit trail', 'basis' => 'Contract / legitimate interest', 'access' => 'Company Admins'],
        ['category' => 'Employees', 'data' => 'Name, ID number (encrypted), contact details, allocation, leave, overtime, allowances, documents', 'purpose' => 'Employment, payroll input, BCEA records', 'basis' => 'Contract and legal obligation (BCEA)', 'access' => 'Workforce managers'],
        ['category' => 'Site attendance', 'data' => 'Sign-in times, location, selfie; crew register', 'purpose' => 'Site access control and time records', 'basis' => 'Legitimate interest and legal obligation', 'access' => 'Site and project managers'],
        ['category' => 'Health and safety', 'data' => 'Incident descriptions, persons involved, appointments and competencies', 'purpose' => 'OHS Act compliance and investigations', 'basis' => 'Legal obligation (OHS Act)', 'access' => 'Safety officers, project managers, directors'],
        ['category' => 'Investors', 'data' => 'Name, ID or registration number (encrypted), contributions, FICA status', 'purpose' => 'Funding records and FICA', 'basis' => 'Contract and legal obligation (FICA)', 'access' => 'Directors and finance'],
        ['category' => 'Supplier contacts', 'data' => 'Contact name, email, phone, compliance documents', 'purpose' => 'Procurement and payments', 'basis' => 'Contract / legitimate interest', 'access' => 'Procurement and finance'],
    ],

    'retention' => [
        'attendance_selfies' => ['label' => 'Site sign-in selfies', 'default_months' => 12, 'minimum_months' => 3],
        'crew_attendance' => ['label' => 'Crew register records', 'default_months' => 36, 'minimum_months' => 36],
        'former_employees' => ['label' => 'Former employees (anonymised, not deleted)', 'default_months' => 36, 'minimum_months' => 36],
        'read_notifications' => ['label' => 'Read notifications', 'default_months' => 12, 'minimum_months' => 1],
        'rfq_links' => ['label' => 'Supplier quote links', 'default_months' => 12, 'minimum_months' => 3],
    ],
];
