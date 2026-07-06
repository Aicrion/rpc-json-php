<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Kernel;

use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Contract\HandlerFactory;
use Aicrion\JsonRpc\Contract\CacheKeyBuilder;
use Aicrion\JsonRpc\Contract\CacheStore;
use Aicrion\JsonRpc\Contract\ParameterBinder;
use Aicrion\JsonRpc\Contract\RpcListener;
use Aicrion\JsonRpc\Pipeline\Stage;
use Aicrion\JsonRpc\Registry\HandlerRegistry;

/**
 * Fluent, immutable builder that assembles a HandlerRegistry from any
 * number of handler instances (each carrying its own #[RpcHandler]
 * namespace), optionally wires custom middleware stages and listeners,
 * and produces a ready-to-use RpcKernel.
 */
final class RpcKernelBuilder
{
    /** @var list<object> */
    private array $handlers = [];

    /** @var list<array{class: string, factory: ?HandlerFactory}> */
    private array $lazyClasses = [];

    /** @var list<array{directory: string, namespace: string, factory: ?HandlerFactory}> */
    private array $discoveryPaths = [];

    /** @var list<Stage> */
    private array $middleware = [];

    /** @var list<RpcListener> */
    private array $listeners = [];

    private ?AuthorizationGate $authorizationGate = null;

    private ?ParameterBinder $parameterBinder = null;

    private ?CacheStore $cacheStore = null;

    private ?CacheKeyBuilder $cacheKeyBuilder = null;

    public function withHandler(object $handler): self
    {
        $clone = clone $this;
        $clone->handlers[] = $handler;

        return $clone;
    }

    /**
     * @param list<object> $handlers
     */
    public function withHandlers(array $handlers): self
    {
        $clone = clone $this;
        $clone->handlers = [...$clone->handlers, ...$handlers];

        return $clone;
    }

    /**
     * Registers a handler by class name only. Nothing is instantiated
     * until one of its methods is actually invoked. Pass a custom
     * HandlerFactory if the handler needs constructor dependencies
     * (e.g. resolved through a DI container).
     */
    public function withHandlerClass(string $handlerClass, ?HandlerFactory $factory = null): self
    {
        $clone = clone $this;
        $clone->lazyClasses[] = ['class' => $handlerClass, 'factory' => $factory];

        return $clone;
    }

    /**
     * Recursively scans $directory for classes annotated with
     * #[RpcHandler] and registers them lazily, using $namespace as
     * the PSR-4 root namespace matching that directory. No handler is
     * instantiated during the scan or at build() time -- construction
     * only happens the first time one of its methods is invoked.
     */
    public function withDiscoveredHandlers(string $directory, string $namespace, ?HandlerFactory $factory = null): self
    {
        $clone = clone $this;
        $clone->discoveryPaths[] = ['directory' => $directory, 'namespace' => $namespace, 'factory' => $factory];

        return $clone;
    }

    public function withAuthorizationGate(AuthorizationGate $gate): self
    {
        $clone = clone $this;
        $clone->authorizationGate = $gate;

        return $clone;
    }

    public function withParameterBinder(ParameterBinder $binder): self
    {
        $clone = clone $this;
        $clone->parameterBinder = $binder;

        return $clone;
    }

    public function withCacheStore(CacheStore $store): self
    {
        $clone = clone $this;
        $clone->cacheStore = $store;

        return $clone;
    }

    public function withCacheKeyBuilder(CacheKeyBuilder $builder): self
    {
        $clone = clone $this;
        $clone->cacheKeyBuilder = $builder;

        return $clone;
    }

    /**
     * Registers a custom pipeline stage, executed after authorization
     * and before parameter binding (e.g. rate limiting, logging, tracing).
     */
    public function withMiddleware(Stage $stage): self
    {
        $clone = clone $this;
        $clone->middleware[] = $stage;

        return $clone;
    }

    public function withListener(RpcListener $listener): self
    {
        $clone = clone $this;
        $clone->listeners[] = $listener;

        return $clone;
    }

    public function build(): RpcKernel
    {
        $registry = new HandlerRegistry($this->handlers);

        foreach ($this->lazyClasses as $entry) {
            $registry->registerClass($entry['class'], $entry['factory']);
        }

        foreach ($this->discoveryPaths as $entry) {
            $registry->discoverPath($entry['directory'], $entry['namespace'], $entry['factory']);
        }

        return new RpcKernel(
            $registry,
            $this->authorizationGate,
            $this->parameterBinder,
            $this->middleware,
            $this->listeners,
            $this->cacheStore,
            $this->cacheKeyBuilder,
        );
    }
}
