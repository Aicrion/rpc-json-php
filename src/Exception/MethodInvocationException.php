<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

use Throwable;

/**
 * Wraps any exception thrown from inside a handler method body so the
 * Kernel can present a normalized "internal error" without leaking
 * implementation details, while still keeping the original cause.
 */
final class MethodInvocationException extends JsonRpcException
{
    public static function fromThrowable(string $method, Throwable $cause): self
    {
        $exception = new self(
            sprintf('Execution of method "%s" failed: %s', $method, $cause->getMessage()),
            -32000,
        );
        $exception->cause = $cause;

        return $exception;
    }

    public ?Throwable $cause = null;
}
