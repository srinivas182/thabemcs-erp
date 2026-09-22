<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\Integration;
use App\Domains\Integrations\Models\IntegrationSync;

/**
 * Loads a company's integration settings and records what was sent, so no record is sent twice.
 */
final class IntegrationRepository
{
    public const array PROVIDERS = ['sage_za' => 'Sage Business Cloud Accounting (South Africa)', 'simplepay' => 'SimplePay payroll'];

    public function get(string $provider): Integration
    {
        return Integration::query()->firstOrCreate(['provider' => $provider], ['enabled' => false, 'settings' => []]);
    }

    public function alreadySent(string $provider, string $type, int $id): bool
    {
        return IntegrationSync::query()->where('provider', $provider)->where('entity_type', $type)->where('entity_id', $id)->where('status', 'sent')->exists();
    }

    public function record(string $provider, string $type, int $id, bool $ok, ?string $externalId, ?string $error): IntegrationSync
    {
        $sync = IntegrationSync::query()->firstOrNew(['provider' => $provider, 'entity_type' => $type, 'entity_id' => $id]);
        $sync->fill(['status' => $ok ? 'sent' : 'failed', 'external_id' => $externalId, 'error' => $error, 'attempts' => $sync->attempts + 1])->save();

        return $sync;
    }

    /**
     * @return array<string, mixed>
     */
    public function setting(Integration $integration, string $key, mixed $default = null): mixed
    {
        return $integration->settings[$key] ?? $default;
    }
}
