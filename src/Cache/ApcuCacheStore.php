<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheStore;
use RuntimeException;

/**
 * APCu-backed cache store. Shares entries across requests within the
 * same PHP-FPM/Apache worker pool without needing an external service.
 */
final class ApcuCacheStore implements CacheStore
{
    public function __construct(
        private readonly string $prefix = 'aicrion_jsonrpc:',
    ) {
        if (!extension_loaded('apcu')) {
            throw new RuntimeException('The apcu extension must be enabled to use ApcuCacheStore.');
        }
    }

    public function get(string $key): mixed
    {
        $value = apcu_fetch($this->prefix . $key, $success);

        return $success ? $value : null;
    }

    public function has(string $key): bool
    {
        return apcu_exists($this->prefix . $key);
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        apcu_store($this->prefix . $key, $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        apcu_delete($this->prefix . $key);
    }

    public function clear(): void
    {
        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
        apcu_delete($iterator);
    }
}
