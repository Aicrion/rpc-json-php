<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Exception\MethodInvocationException;
use Aicrion\JsonRpc\Message\RpcRequest;
use Throwable;

/**
 * Terminal stage of the pipeline: calls the resolved handler method
 * with the bound arguments and returns its raw return value.
 */
final class InvokeHandlerStage implements Stage
{
    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;

        try {
            return $descriptor->handlerInstance->{$descriptor->handlerMethod}(...$context->boundArguments);
        } catch (Throwable $throwable) {
            throw MethodInvocationException::fromThrowable($descriptor->qualifiedName, $throwable);
        }
    }
}
