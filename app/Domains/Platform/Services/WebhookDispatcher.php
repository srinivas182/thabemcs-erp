<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Jobs\DeliverWebhook;
use App\Domains\Platform\Models\Webhook;
use App\Domains\Platform\Models\WebhookDelivery;
use App\Support\Tenancy\CurrentCompany;

/**
 * Tells other systems when something happens here. Each delivery is signed with the webhook's secret,
 * so the receiver can check it came from us, and retried a few times if it fails.
 */
final class WebhookDispatcher
{
    public function __construct(private readonly CurrentCompany $context) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $event, array $payload): int
    {
        $company = $this->context->get();
        if ($company === null) {
            return 0;
        }

        $sent = 0;
        foreach (Webhook::query()->where('active', true)->get() as $webhook) {
            if (! in_array($event, $webhook->events, true)) {
                continue;
            }
            $delivery = WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id, 'event' => $event,
                'payload' => ['event' => $event, 'company' => $company->name, 'sentAt' => now()->toIso8601String(), 'data' => $payload],
            ]);
            DeliverWebhook::dispatch((int) $company->getKey(), $delivery->id);
            $sent++;
        }

        return $sent;
    }

    public static function signature(string $secret, string $body): string
    {
        return hash_hmac('sha256', $body, $secret);
    }
}
