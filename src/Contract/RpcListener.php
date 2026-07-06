<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

use Aicrion\JsonRpc\Registry\MethodDescriptor;
use Throwable;

/**
 * Observer hook fired around method invocation. Useful for logging,
 * metrics, tracing, or audit trails without touching the pipeline.
 */
interface RpcListener
{
    /**
     * @param list<mixed> $arguments
     */
    public function beforeInvoke(MethodDescriptor $descriptor, array $arguments): void;

    public function afterInvoke(MethodDescriptor $descriptor, mixed $result): void;

    public function onFailure(MethodDescriptor $descriptor, Throwable $exception): void;
}
