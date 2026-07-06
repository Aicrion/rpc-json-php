<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Attribute\Cacheable;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

/**
 * Exposes a method that returns a fresh random value each call, used
 * to prove the CachingStage actually returns a memoized result.
 */
#[RpcHandler('clock')]
final class ClockHandler
{
    public int $callCount = 0;

    #[RpcMethod]
    #[Cacheable(ttlSeconds: 60)]
    public function now(string $zone): int
    {
        $this->callCount++;

        return $this->callCount;
    }

    #[RpcMethod]
    public function uncached(): int
    {
        $this->callCount++;

        return $this->callCount;
    }
}
