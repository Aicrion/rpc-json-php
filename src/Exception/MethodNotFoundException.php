<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

final class MethodNotFoundException extends JsonRpcException
{
    public static function forMethod(string $method): self
    {
        return new self(sprintf('No handler is registered for method "%s".', $method), -32601);
    }
}
