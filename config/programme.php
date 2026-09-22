<?php

declare(strict_types=1);

/*
| Programme calendar. The building industry closes over the December holidays; those days are not
| working days on the programme. Set PROGRAMME_SHUTDOWN_FROM= (empty) to switch it off.
*/

return [
    'shutdown' => [
        'from' => env('PROGRAMME_SHUTDOWN_FROM', '12-16'),
        'to' => env('PROGRAMME_SHUTDOWN_TO', '01-09'),
    ],
];
