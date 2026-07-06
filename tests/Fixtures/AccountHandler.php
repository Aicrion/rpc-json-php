<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Attribute\Protected_;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Attribute\Unprotected;

/**
 * Every method here is protected automatically because the class
 * itself carries #[Protected_] -- no per-method attribute needed.
 */
#[RpcHandler('account')]
#[Protected_]
final class AccountHandler
{
    #[RpcMethod]
    public function balance(string $accountId): array
    {
        return ['accountId' => $accountId, 'balance' => 100];
    }

    #[RpcMethod]
    #[Unprotected]
    public function ping(): string
    {
        return 'pong';
    }
}
