<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\CacheKeyBuilder;
use Aicrion\JsonRpc\Contract\CacheStore;
use Aicrion\JsonRpc\Message\RpcRequest;

/**
 * Consults the resolved descriptor's caching policy (derived from
 * #[Cacheable] during registration) and short-circuits the pipeline
 * with a stored result when available, otherwise executes the rest
 * of the chain and persists its result under the computed key.
 *
 * Placed after parameter binding so the cache key can be derived
 * from the final, bound argument list.
 */
final class CachingStage implements Stage
{
    public function __construct(
        private readonly CacheStore $store,
        private readonly CacheKeyBuilder $keyBuilder,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;

        if (!$descriptor->isCacheable()) {
            return $next($request, $context);
        }

        $key = $this->keyBuilder->build($descriptor, $context->boundArguments);

        if ($this->store->has($key)) {
            return $this->store->get($key);
        }

        $result = $next($request, $context);
        $this->store->set($key, $result, $descriptor->cacheTtlSeconds);

        return $result;
    }
}
