<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Attribute;

use Attribute;

/**
 * Marks an RPC method's result as cacheable. The CachingStage reads
 * this attribute (resolved once by the HandlerRegistry) to decide
 * whether to consult the configured CacheStore before invocation.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Cacheable
{
    public function __construct(
        public readonly int $ttlSeconds = 60,
    ) {
    }
}
