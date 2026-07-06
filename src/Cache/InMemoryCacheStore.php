<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheStore;

/**
 * Process-local cache backed by a plain array. Ideal for CLI scripts,
 * tests, or single-request-lifetime workers. Not shared across processes.
 */
final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expiresAt: float}> */
    private array $entries = [];

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->entries[$key]['value'];
    }

    public function has(string $key): bool
    {
        if (!isset($this->entries[$key])) {
            return false;
        }

        if ($this->entries[$key]['expiresAt'] < microtime(true)) {
            unset($this->entries[$key]);

            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expiresAt' => microtime(true) + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
