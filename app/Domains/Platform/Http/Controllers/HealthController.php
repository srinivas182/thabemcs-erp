<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Readiness check for the load balancer and monitoring: the server only takes traffic when it can
 * reach the database, the cache, the queue and file storage. Laravel's own /up covers liveness.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn () => DB::connection()->select('select 1')),
            'cache' => $this->check(static function (): void {
                cache()->put('health', 'ok', 10);
                if (cache()->get('health') !== 'ok') {
                    throw new \RuntimeException('Cache did not return what was written.');
                }
            }),
            'queue' => $this->check(static fn () => Queue::connection()->size()),
            'storage' => $this->check(static fn () => Storage::disk((string) config('platform.documents_disk'))->exists('.')),
        ];

        $ok = ! in_array(false, array_map(static fn (array $c): bool => $c['ok'], $checks), true);

        return response()->json([
            'status' => $ok ? 'ready' : 'degraded',
            'checks' => $checks,
            'version' => config('app.version'),
        ], $ok ? 200 : 503);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
