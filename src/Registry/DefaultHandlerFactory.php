<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\HandlerFactory;

/**
 * Instantiates a handler with a plain `new $class()`, assuming a
 * parameterless constructor. Swap for a container-backed factory
 * (see ContainerHandlerFactory) when handlers need dependencies.
 */
final class DefaultHandlerFactory implements HandlerFactory
{
    public function create(string $handlerClass): object
    {
        return new $handlerClass();
    }
}
