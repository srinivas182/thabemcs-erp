<?php

declare(strict_types=1);
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\Employee;

/*
| Opening data imports. Each type lists the columns in the template, which are required, and how they
| map onto the system. Files are CSV (save an Excel sheet as CSV) with a header row.
*/

return [
    'suppliers' => [
        'label' => 'Suppliers and contractors',
        'model' => Supplier::class,
        'unique' => 'name',
        'columns' => [
            'name' => ['label' => 'Name', 'required' => true],
            'type' => ['label' => 'Type (contractor, subcontractor, supplier, plant_hire, consultant, estate_agency, other)', 'required' => true],
            'registration_number' => ['label' => 'Company registration number', 'required' => false],
            'vat_number' => ['label' => 'VAT number', 'required' => false],
            'cidb_grade' => ['label' => 'CIDB grade (1-9)', 'required' => false],
            'bbbee_level' => ['label' => 'B-BBEE level', 'required' => false],
            'contact_name' => ['label' => 'Contact person', 'required' => false],
            'email' => ['label' => 'Email', 'required' => false],
            'phone' => ['label' => 'Phone', 'required' => false],
        ],
    ],
    'employees' => [
        'label' => 'Employees',
        'model' => Employee::class,
        'unique' => 'employee_number',
        'columns' => [
            'employee_number' => ['label' => 'Employee number', 'required' => true],
            'first_name' => ['label' => 'First name', 'required' => true],
            'last_name' => ['label' => 'Surname', 'required' => true],
            'job_title' => ['label' => 'Job title', 'required' => false],
            'employment_type' => ['label' => 'Employment type (permanent, fixed_term, temporary)', 'required' => true],
            'start_date' => ['label' => 'Start date (YYYY-MM-DD)', 'required' => true],
            'days_per_week' => ['label' => 'Days worked a week', 'required' => false],
            'phone' => ['label' => 'Phone', 'required' => false],
        ],
    ],
    'units' => [
        'label' => 'Units for sale or to let',
        'model' => SaleUnit::class,
        'unique' => 'reference',
        'project' => true,
        'columns' => [
            'reference' => ['label' => 'Erf or unit number', 'required' => true],
            'type' => ['label' => 'Type (erf, house, sectional_unit, commercial)', 'required' => true],
            'description' => ['label' => 'Description', 'required' => false],
            'size_m2' => ['label' => 'Size in m²', 'required' => false],
            'bedrooms' => ['label' => 'Bedrooms', 'required' => false],
            'list_price' => ['label' => 'Asking price incl. VAT', 'required' => false],
            'market_rent' => ['label' => 'Market rent a month', 'required' => false],
            'tenure' => ['label' => 'For sale, rental or both', 'required' => false],
            'nhbrc_enrolment' => ['label' => 'NHBRC enrolment number', 'required' => false],
        ],
    ],
    'budget_lines' => [
        'label' => 'Project budget',
        'model' => BudgetLine::class,
        'unique' => 'code',
        'project' => true,
        'columns' => [
            'code' => ['label' => 'Cost code', 'required' => true],
            'description' => ['label' => 'Description', 'required' => true],
            'original_amount' => ['label' => 'Budget excl. VAT', 'required' => true],
        ],
    ],
    'tenants' => [
        'label' => 'Tenants',
        'model' => Tenant::class,
        'unique' => 'name',
        'columns' => [
            'name' => ['label' => 'Name', 'required' => true],
            'entity_type' => ['label' => 'Individual, company or trust', 'required' => false],
            'email' => ['label' => 'Email', 'required' => false],
            'phone' => ['label' => 'Phone', 'required' => false],
            'employer' => ['label' => 'Employer', 'required' => false],
            'status' => ['label' => 'Status (applicant, approved, current, former)', 'required' => false],
        ],
    ],
];
