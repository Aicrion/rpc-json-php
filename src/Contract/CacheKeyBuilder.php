<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

use Aicrion\JsonRpc\Registry\MethodDescriptor;

/**
 * Computes the cache key used to store/retrieve a method's result.
 * Applications may swap this to control key granularity (e.g. include
 * a tenant id, a user id, an API version, etc.).
 */
interface CacheKeyBuilder
{
    /**
     * @param list<mixed> $arguments
     */
    public function build(MethodDescriptor $descriptor, array $arguments): string;
}
