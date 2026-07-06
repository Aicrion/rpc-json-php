<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

/**
 * Produces a handler instance on demand. Implementations may wrap a
 * DI container (PSR-11), a service locator, or simply `new $class()`.
 * The registry never instantiates a handler until one of its methods
 * is actually invoked.
 */
interface HandlerFactory
{
    public function create(string $handlerClass): object;
}
