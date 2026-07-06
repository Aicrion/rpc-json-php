<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheStore;
use Redis;

/**
 * Redis-backed cache store for distributed / multi-server deployments.
 * Accepts any already-connected \Redis (or \Predis-compatible client
 * exposing the same method names) instance so this library never owns
 * connection management.
 */
final class RedisCacheStore implements CacheStore
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'aicrion_jsonrpc:',
    ) {
    }

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($this->prefix . $key);

        return $raw === false ? null : unserialize($raw);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->redis->set($this->prefix . $key, serialize($value), ['EX' => $ttlSeconds]);
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->prefix . $key);
    }

    public function clear(): void
    {
        $cursor = null;
        do {
            $keys = $this->redis->scan($cursor, $this->prefix . '*');
            if ($keys) {
                $this->redis->del($keys);
            }
        } while ($cursor !== 0 && $cursor !== null);
    }
}
