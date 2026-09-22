<?php

declare(strict_types=1);

return [

    /*
     | Initial Super Admin, created by the PlatformSeeder on first install.
     | Change the password immediately after the first sign-in.
     */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Platform Administrator'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@thabekhulu.local'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMe!2026'),
    ],

];
