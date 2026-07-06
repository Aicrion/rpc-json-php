<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Exception\UnauthorizedMethodException;
use Aicrion\JsonRpc\Message\RpcRequest;

/**
 * Consults the descriptor's automatically computed "isProtected" flag
 * (derived from #[Protected_] / #[Unprotected] during registration) and
 * delegates the actual yes/no decision to an AuthorizationGate. Because
 * protection is resolved once at registry build time, no per-method
 * boilerplate is needed anywhere in application code.
 */
final class AuthorizationStage implements Stage
{
    public function __construct(
        private readonly AuthorizationGate $gate,
    ) {
    }

    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        $descriptor = $context->descriptor;

        if ($descriptor?->isProtected === true && !$this->gate->isAuthorized($descriptor, $request->params)) {
            throw UnauthorizedMethodException::forMethod($descriptor->qualifiedName);
        }

        return $next($request, $context);
    }
}
