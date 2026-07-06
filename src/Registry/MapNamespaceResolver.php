<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\NamespaceResolver;

/**
 * Explicit namespace => class-name map, for cases where the naming
 * convention doesn't fit and you'd rather declare the mapping once,
 * still without ever instantiating anything upfront.
 */
final class MapNamespaceResolver implements NamespaceResolver
{
    /**
     * @param array<string, class-string> $map
     */
    public function __construct(
        private readonly array $map,
    ) {
    }

    public function resolve(string $rpcNamespace): ?string
    {
        $class = $this->map[$rpcNamespace] ?? null;

        return $class !== null && class_exists($class) ? $class : null;
    }
}
