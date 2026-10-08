<?php

declare(strict_types=1);

return [
    // Where database backups are written (see config/filesystems.php and docs/disaster-recovery.md).
    'backup_disk' => env('BACKUP_DISK', 'backups'),

    /*
    | Roles that must have two-factor even when it is not required of everyone. Set TWO_FACTOR_ROLES to a
    | comma-separated list, or to "none" to require it of nobody, which is only sensible on a test or
    | demonstration instance.
    */
    'two_factor_required_roles' => env('TWO_FACTOR_ROLES') === 'none' ? [] : array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TWO_FACTOR_ROLES', 'company-admin,director,finance,development-manager')),
    ))),

    // Once the website is public, everyone who signs in needs a second factor. Set to false only for a
    // deployment where that is genuinely impossible, and record why.
    'two_factor_required_for_all' => (bool) env('REQUIRE_TWO_FACTOR', true),

    /*
     | Initial Super Admin, created by the PlatformSeeder on first install.
     | Change the password immediately after the first sign-in.
     */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Platform Administrator'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@thabekhulu.local'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMe!2026'),
    ],

    /*
     | Disk for project documents and compliance files ('documents' locally, 's3' in production).
     */
    'documents_disk' => env('DOCUMENTS_DISK', 'documents'),

    /*
     | Allowed upload types and size limit (KB).
     */
    'upload_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'dwg', 'dxf', 'zip', 'txt'],
    'upload_max_kb' => 51200,

];
