<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\WebhookDelivery;
use App\Domains\Platform\Services\WebhookDispatcher;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $companyId, public readonly int $deliveryId)
    {
        $this->onQueue('integrations');
        $this->afterCommit();
    }

    public function handle(CurrentCompany $context): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null) {
            return;
        }

        $context->runFor($company, function (): void {
            $delivery = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);
            if ($delivery === null || $delivery->delivered_at !== null) {
                return;
            }
            $webhook = $delivery->webhook;
            $body = (string) json_encode($delivery->payload);

            try {
                $response = Http::timeout((int) config('webhooks.timeout_seconds', 10))
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Thabekhulu-Event' => $delivery->event,
                        'X-Thabekhulu-Signature' => WebhookDispatcher::signature((string) $webhook->secret, $body),
                    ])
                    ->withBody($body, 'application/json')
                    ->post($webhook->url);

                $delivery->update([
                    'response_status' => $response->status(),
                    'attempts' => $delivery->attempts + 1,
                    'delivered_at' => $response->successful() ? now() : null,
                    'error' => $response->successful() ? null : 'The receiver answered '.$response->status().'.',
                ]);
                $webhook->update($response->successful()
                    ? ['last_delivered_at' => now(), 'last_error' => null, 'failures' => 0]
                    : ['last_error' => 'Answered '.$response->status(), 'failures' => $webhook->failures + 1]);

                // Try again shortly, backing off, until the maximum number of attempts.
                if (! $response->successful() && $delivery->attempts + 1 < (int) config('webhooks.max_attempts', 5)) {
                    self::dispatch($this->companyId, $this->deliveryId)->delay(now()->addMinutes(5 * ($delivery->attempts + 1)));
                }
            } catch (Throwable $e) {
                $delivery->update(['attempts' => $delivery->attempts + 1, 'error' => mb_substr($e->getMessage(), 0, 190)]);
                $webhook->update(['last_error' => mb_substr($e->getMessage(), 0, 190), 'failures' => $webhook->failures + 1]);
                if ($delivery->attempts < (int) config('webhooks.max_attempts', 5)) {
                    self::dispatch($this->companyId, $this->deliveryId)->delay(now()->addMinutes(5 * $delivery->attempts));
                }
            }
        });
    }
}
