<?php

declare(strict_types=1);

/*
| Branding on the sign-in screens and emails. Everything is set per deployment in .env,
| so the same code serves Thabekhulu or any other client.
|
| Logo: put the client's logo at public/branding/logo.svg (or .png) and set BRAND_LOGO=/branding/logo.svg.
| Leave BRAND_LOGO empty to show the text wordmark instead.
*/

return [
    'name' => env('BRAND_NAME', 'Thabekhulu Development Software'),
    'short_name' => env('BRAND_SHORT_NAME', 'Thabekhulu'),
    'owner' => env('BRAND_OWNER', 'Steve Maqueens and Thabekhulu Development Group'),
    'tagline' => env('BRAND_TAGLINE', 'Plan, fund, approve, build, sell and close every development from one place.'),
    'logo' => env('BRAND_LOGO'),

    'support' => [
        'email' => env('SUPPORT_EMAIL'),
        'phone' => env('SUPPORT_PHONE'),
        'hours' => env('SUPPORT_HOURS', 'Monday to Friday, 08:00 to 17:00'),
    ],

    // Link to the organisation's POPIA privacy notice (optional).
    'privacy_url' => env('BRAND_PRIVACY_URL'),

    // Shown small at the bottom of the sign-in page; set BRAND_POWERED_BY= (empty) to hide.
    'powered_by' => env('BRAND_POWERED_BY', 'Mayura Consultancy Services'),
];
