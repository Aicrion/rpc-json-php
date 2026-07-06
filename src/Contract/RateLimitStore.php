<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

/**
 * Abstraction over a counter store used for rate limiting. Implementations
 * may be local (in-memory, single process) or distributed (Redis,
 * Memcached) so the same DistributedRateLimitStage works identically
 * in both single-server and multi-server deployments.
 */
interface RateLimitStore
{
    /**
     * Increments the counter for $key and returns the new value. The
     * first increment for a key must (re)start the expiry window.
     */
    public function increment(string $key, int $windowSeconds): int;
}
