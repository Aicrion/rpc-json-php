<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\HandlerFactory;

/**
 * Trivial factory that simply returns an already-constructed handler
 * instance. Used internally to make eagerly-registered handlers share
 * the exact same LazyHandler contract as auto-discovered ones, so the
 * rest of the pipeline never needs to know which path a handler came
 * from.
 */
final class InstanceHandlerFactory implements HandlerFactory
{
    public function __construct(
        private readonly object $instance,
    ) {
    }

    public function create(string $handlerClass): object
    {
        return $this->instance;
    }
}
