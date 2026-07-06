<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

final class RateLimitExceededException extends JsonRpcException
{
    public static function forMethod(string $method, int $limit): self
    {
        return new self(
            sprintf('Method "%s" exceeded its call limit of %d.', $method, $limit),
            -32002,
        );
    }
}
