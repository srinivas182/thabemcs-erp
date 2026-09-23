<?php

declare(strict_types=1);

/*
| Investor returns. Cash is distributed in order: capital back first, then the preferred return,
| then the remaining profit. Confirm the terms against each investment agreement (SA assumptions DI1).
*/

return [
    // Used when a funding source does not state its own preferred return.
    'preferred_return_percent' => (float) env('DISTRIBUTION_PREFERRED_RETURN', 0.0),

    'order' => ['capital', 'preferred', 'profit'],
];
