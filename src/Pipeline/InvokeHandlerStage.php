<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Exception\MethodInvocationException;
use Aicrion\JsonRpc\Message\RpcRequest;
use Throwable;

/**
 * Terminal stage of the pipeline: resolves the handler instance
 * lazily (constructing it only now, on the first real call that
 * needs it) and invokes it with the bound arguments.
 */
final class InvokeHandlerStage implements Stage
{
    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;

        try {
            $instance = $descriptor->handler->resolve();

            return $instance->{$descriptor->handlerMethod}(...$context->boundArguments);
        } catch (Throwable $throwable) {
            throw MethodInvocationException::fromThrowable($descriptor->qualifiedName, $throwable);
        }
    }
}
