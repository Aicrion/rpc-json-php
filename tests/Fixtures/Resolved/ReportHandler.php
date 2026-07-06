<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures\Resolved;

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

#[RpcHandler('report')]
final class ReportHandler
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    #[RpcMethod]
    public function generate(): string
    {
        return 'report-generated';
    }
}
