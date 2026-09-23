<?php

declare(strict_types=1);

/*
| Rows a report or dataset returns on screen and in exports. Reports say when they are cut short so a
| filtered report never silently misses rows.
*/

return [
    'row_limit' => (int) env('REPORT_ROW_LIMIT', 5000),
];
