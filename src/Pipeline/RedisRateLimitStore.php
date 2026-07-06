<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\RateLimitStore;
use Redis;

/**
 * Distributed rate limiting mode backed by Redis, so the same limit
 * is enforced consistently across every server/worker in a cluster.
 */
final class RedisRateLimitStore implements RateLimitStore
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'aicrion_jsonrpc:rl:',
    ) {
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $redisKey = $this->prefix . $key;
        $count = (int) $this->redis->incr($redisKey);

        if ($count === 1) {
            $this->redis->expire($redisKey, $windowSeconds);
        }

        return $count;
    }
}
