<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\RpcListener;
use Aicrion\JsonRpc\Message\RpcRequest;
use Throwable;

/**
 * Wraps the remaining pipeline with beforeInvoke/afterInvoke/onFailure
 * notifications sent to every registered RpcListener. Placed just
 * before InvokeHandlerStage so listeners see the fully resolved
 * descriptor and bound arguments.
 */
final class NotifyListenersStage implements Stage
{
    /**
     * @param list<RpcListener> $listeners
     */
    public function __construct(
        private readonly array $listeners,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;

        foreach ($this->listeners as $listener) {
            $listener->beforeInvoke($descriptor, $context->boundArguments);
        }

        try {
            $result = $next($request, $context);
        } catch (Throwable $exception) {
            foreach ($this->listeners as $listener) {
                $listener->onFailure($descriptor, $exception);
            }

            throw $exception;
        }

        foreach ($this->listeners as $listener) {
            $listener->afterInvoke($descriptor, $result);
        }

        return $result;
    }
}
