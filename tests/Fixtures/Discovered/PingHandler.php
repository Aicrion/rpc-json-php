<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures\Discovered;

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

#[RpcHandler('discovered')]
final class PingHandler
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    #[RpcMethod]
    public function ping(): string
    {
        return 'pong-from-discovery';
    }
}
