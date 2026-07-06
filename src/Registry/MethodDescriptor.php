<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

/**
 * Immutable, fully resolved record describing one callable RPC
 * procedure: which handler backs it (wrapped in a LazyHandler so the
 * concrete instance is only built on first real invocation), its
 * public name ("namespace.method"), whether it is protected, and its
 * caching policy (if any) -- all resolved once at registration time.
 */
final class MethodDescriptor
{
    public function __construct(
        public readonly string $qualifiedName,
        public readonly LazyHandler $handler,
        public readonly string $handlerMethod,
        public readonly bool $isProtected,
        public readonly ?int $cacheTtlSeconds = null,
    ) {
    }

    public function isCacheable(): bool
    {
        return $this->cacheTtlSeconds !== null;
    }
}
