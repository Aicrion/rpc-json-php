<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheStore;

/**
 * Filesystem-backed cache that persists entries as serialized PHP
 * across process boundaries and requests -- suitable for CLI tools,
 * single-server deployments, or as a fallback when no external cache
 * service (Redis/Memcached/APCu) is available.
 */
final class FileCacheStore implements CacheStore
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        $payload = unserialize((string) file_get_contents($this->pathFor($key)));

        return $payload['value'];
    }

    public function has(string $key): bool
    {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return false;
        }

        $payload = unserialize((string) file_get_contents($path));

        if (!is_array($payload) || $payload['expiresAt'] < time()) {
            @unlink($path);

            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $payload = ['value' => $value, 'expiresAt' => time() + $ttlSeconds];
        file_put_contents($this->pathFor($key), serialize($payload));
    }

    public function delete(string $key): void
    {
        @unlink($this->pathFor($key));
    }

    public function clear(): void
    {
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function pathFor(string $key): string
    {
        return $this->directory . '/' . hash('xxh128', $key) . '.cache';
    }
}
