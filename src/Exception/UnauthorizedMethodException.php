<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

final class UnauthorizedMethodException extends JsonRpcException
{
    public static function forMethod(string $method): self
    {
        return new self(sprintf('Method "%s" requires authorization.', $method), -32001);
    }
}
