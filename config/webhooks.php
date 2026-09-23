<?php

declare(strict_types=1);

/*
| Events other systems can subscribe to. Every delivery is signed with the webhook's secret in the
| X-Thabekhulu-Signature header (HMAC-SHA256 of the body), so the receiver can check it is genuine.
*/

return [
    'events' => [
        'invoice.approved' => 'A supplier invoice was approved for payment',
        'purchase_order.issued' => 'A purchase order was issued',
        'sale.registered' => 'A sale transferred in the Deeds Office',
        'lease.started' => 'A lease was activated',
        'incident.reported' => 'A health and safety incident was reported',
        'project.completed' => 'A project was closed out',
    ],

    'timeout_seconds' => 10,
    'max_attempts' => 5,
];
