<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to send an event to a webhook.
 *
 * @property int $id
 * @property int $webhook_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int|null $response_status
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $delivered_at
 * @property Carbon $created_at
 * @property-read Webhook $webhook
 */
class WebhookDelivery extends Model
{
    use BelongsToCompany;

    protected $fillable = ['webhook_id', 'event', 'payload', 'response_status', 'error', 'attempts', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime', 'attempts' => 'integer', 'response_status' => 'integer'];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
