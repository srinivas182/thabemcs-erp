<?php

declare(strict_types=1);

return [
    // Where database backups are written (see config/filesystems.php and docs/disaster-recovery.md).
    'backup_disk' => env('BACKUP_DISK', 'backups'),

    /*
    | Roles that must have two-factor authentication switched on: anyone who can approve work,
    | move money or change what other people may do.
    */
    'two_factor_required_roles' => ['company-admin', 'director', 'finance', 'development-manager'],

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
