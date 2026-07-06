<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\ParameterBinder;
use Aicrion\JsonRpc\Message\RpcRequest;
use ReflectionMethod;

/**
 * Reflects the target handler method's signature (by class name --
 * this never triggers instantiation) and binds request params onto
 * its parameter list, storing the resulting argument vector.
 */
final class BindParametersStage implements Stage
{
    public function __construct(
        private readonly ParameterBinder $binder,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;
        $reflectionMethod = new ReflectionMethod($descriptor->handler->handlerClass, $descriptor->handlerMethod);
        $context->boundArguments = $this->binder->bind($reflectionMethod, $request->params);

        return $next($request, $context);
    }
}
