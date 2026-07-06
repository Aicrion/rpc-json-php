<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

/**
 * Minimal cache abstraction the library depends on. Applications can
 * plug in any storage medium (memory, file, APCu, Redis, Memcached...)
 * by implementing this interface -- the CachingStage never knows which
 * backend it is talking to.
 */
interface CacheStore
{
    public function get(string $key): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    public function delete(string $key): void;

    public function clear(): void;
}
