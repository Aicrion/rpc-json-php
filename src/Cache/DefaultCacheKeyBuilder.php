<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Cache;

use Aicrion\JsonRpc\Contract\CacheKeyBuilder;
use Aicrion\JsonRpc\Registry\MethodDescriptor;

/**
 * Builds a deterministic cache key from the method's qualified name
 * and a stable hash of its bound arguments.
 */
final class DefaultCacheKeyBuilder implements CacheKeyBuilder
{
    public function build(MethodDescriptor $descriptor, array $arguments): string
    {
        return $descriptor->qualifiedName . ':' . hash('xxh128', serialize($arguments));
    }
}
