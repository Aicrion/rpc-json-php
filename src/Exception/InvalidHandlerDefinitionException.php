<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

final class InvalidHandlerDefinitionException extends JsonRpcException
{
    public static function missingNamespaceAttribute(string $class): self
    {
        return new self(
            sprintf('Class "%s" must be annotated with #[RpcHandler] to be registered.', $class),
            -32603,
        );
    }

    public static function duplicateMethod(string $method): self
    {
        return new self(
            sprintf('Method "%s" is already registered by another handler.', $method),
            -32603,
        );
    }
}
