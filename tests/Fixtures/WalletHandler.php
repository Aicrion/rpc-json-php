<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Exception\RpcErrorException;
use RuntimeException;

#[RpcHandler('wallet')]
final class WalletHandler
{
    #[RpcMethod]
    public function withdraw(float $amount): array
    {
        if ($amount > 100) {
            throw new RpcErrorException('Insufficient funds', -32020, ['available' => 100, 'requested' => $amount]);
        }

        return ['withdrawn' => $amount];
    }

    #[RpcMethod]
    public function brokenMethod(): void
    {
        throw new RuntimeException('Something unexpected blew up internally.');
    }
}
