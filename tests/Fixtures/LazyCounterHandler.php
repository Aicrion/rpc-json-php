<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

/**
 * Increments a static counter in its constructor so tests can prove
 * whether or how many times this handler was actually instantiated.
 */
#[RpcHandler('lazy')]
final class LazyCounterHandler
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    #[RpcMethod]
    public function ping(): string
    {
        return 'pong';
    }
}
