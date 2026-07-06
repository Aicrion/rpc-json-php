<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Exception\MethodNotFoundException;
use Aicrion\JsonRpc\Message\RpcRequest;
use Aicrion\JsonRpc\Registry\HandlerRegistry;

/**
 * Looks up the qualified method name ("namespace.method") in the
 * registry and stores the resolved descriptor on the context.
 */
final class ResolveMethodStage implements Stage
{
    public function __construct(
        private readonly HandlerRegistry $registry,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $this->registry->find($request->method);

        if ($descriptor === null) {
            throw MethodNotFoundException::forMethod($request->method);
        }

        $context->descriptor = $descriptor;

        return $next($request, $context);
    }
}
