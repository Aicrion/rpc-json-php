<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\RateLimitStore;

/**
 * Process-local counter store, i.e. the "single server" rate limiting
 * mode. Counts reset when the PHP process/request ends.
 */
final class LocalRateLimitStore implements RateLimitStore
{
    /** @var array<string, array{count: int, resetAt: int}> */
    private array $counters = [];

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();

        if (!isset($this->counters[$key]) || $this->counters[$key]['resetAt'] <= $now) {
            $this->counters[$key] = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        return ++$this->counters[$key]['count'];
    }
}
