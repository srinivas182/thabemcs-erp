<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * The only way domain code caches data. Every key is prefixed with the current company, so one company's
 * figures can never be served to another; an arch test forbids using the Cache facade directly.
 *
 * Invalidation uses versioned groups rather than cache tags, so it works on every cache store:
 * flush('portfolio') bumps that group's version for the current company and old entries simply expire.
 */
final class CompanyCache
{
    public function __construct(private readonly Repository $cache, private readonly CurrentCompany $context) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $group, string $key, int $seconds, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($group, $key), $seconds, $callback);
    }

    public function flush(string $group): void
    {
        $versionKey = $this->prefix()."v:{$group}";
        if (! $this->cache->add($versionKey, 2, 86400 * 30)) {
            $this->cache->increment($versionKey);
        }
    }

    public function key(string $group, string $key): string
    {
        $version = (int) $this->cache->get($this->prefix()."v:{$group}", 1);

        return $this->prefix()."{$group}:{$version}:{$key}";
    }

    private function prefix(): string
    {
        $id = $this->context->get()?->getKey();

        // Platform-level (no company) entries get their own namespace, never a company's.
        return $id === null ? 'platform:' : "c{$id}:";
    }
}
