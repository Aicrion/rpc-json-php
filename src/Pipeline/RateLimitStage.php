<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\RateLimitStore;
use Aicrion\JsonRpc\Exception\RateLimitExceededException;
use Aicrion\JsonRpc\Message\RpcRequest;

/**
 * Rate limiting middleware, decoupled from its storage backend via
 * RateLimitStore. Use LocalRateLimitStore for a single-process/single
 * -server setup, or RedisRateLimitStore to enforce the same limit
 * across a distributed cluster -- swapping backends never touches
 * this stage or the rest of the pipeline.
 */
final class RateLimitStage implements Stage
{
    public function __construct(
        private readonly RateLimitStore $store,
        private readonly int $maxCallsPerMethod,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $method = $context->descriptor->qualifiedName;
        $count = $this->store->increment($method, $this->windowSeconds);

        if ($count > $this->maxCallsPerMethod) {
            throw RateLimitExceededException::forMethod($method, $this->maxCallsPerMethod);
        }

        return $next($request, $context);
    }
}
