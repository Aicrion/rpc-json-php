<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\HandlerFactory;

/**
 * Wraps a handler class name and defers its instantiation until
 * resolve() is called for the first time. The resulting instance is
 * memoized so every method belonging to the same class shares a
 * single instance for the lifetime of the registry, exactly like the
 * eager registration path -- the only difference is *when* the
 * constructor runs.
 */
final class LazyHandler
{
    private ?object $instance = null;

    public function __construct(
        public readonly string $handlerClass,
        private readonly HandlerFactory $factory,
    ) {
    }

    public function resolve(): object
    {
        return $this->instance ??= $this->factory->create($this->handlerClass);
    }

    public function isResolved(): bool
    {
        return $this->instance !== null;
    }
}
