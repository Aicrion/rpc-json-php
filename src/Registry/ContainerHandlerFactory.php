<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\HandlerFactory;
use Psr\Container\ContainerInterface;

/**
 * Resolves handlers through any PSR-11 container, so constructor
 * dependencies (database connections, loggers, services...) are
 * injected the same way the rest of the application resolves them.
 */
final class ContainerHandlerFactory implements HandlerFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function create(string $handlerClass): object
    {
        return $this->container->get($handlerClass);
    }
}
