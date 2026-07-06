<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

#[RpcHandler('math')]
final class MathHandler
{
    #[RpcMethod]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[RpcMethod(alias: 'multiply')]
    public function mul(int $a, int $b): int
    {
        return $a * $b;
    }

    public function notExposed(): string
    {
        return 'should never be reachable via RPC';
    }
}
