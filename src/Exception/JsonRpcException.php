<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

use RuntimeException;

/**
 * Base type for every exception this library throws. Carries the
 * JSON-RPC 2.0 numeric error code so the Kernel can translate any
 * caught exception directly into an RpcError.
 */
abstract class JsonRpcException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $rpcCode,
        public readonly mixed $rpcData = null,
    ) {
        parent::__construct($message);
    }
}
