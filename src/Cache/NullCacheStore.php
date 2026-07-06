<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheStore;

/**
 * No-op cache store used by default when caching is not configured.
 * Guarantees CachingStage stays a harmless pass-through.
 */
final class NullCacheStore implements CacheStore
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
    }

    public function delete(string $key): void
    {
    }

    public function clear(): void
    {
    }
}
