<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Message\RpcRequest;

/**
 * A single link in the request-processing pipeline. Each stage may
 * inspect/transform state and must call $next to continue the chain,
 * or short-circuit by returning its own result.
 */
interface Stage
{
    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed;
}
