<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

use ReflectionMethod;

/**
 * Maps raw JSON-RPC params (positional or named) onto the concrete
 * argument list expected by a handler method's reflection signature.
 */
interface ParameterBinder
{
    /**
     * @param array<string, mixed>|list<mixed> $params
     * @return list<mixed>
     */
    public function bind(ReflectionMethod $method, array $params): array;
}
