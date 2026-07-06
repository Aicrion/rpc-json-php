<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Contract\NamespaceResolver;

/**
 * Default naming convention: an RPC namespace such as "math" maps to
 * "{$baseNamespace}\MathHandler" (PascalCase + a configurable suffix).
 * Since this only builds a class *name* string, resolving it is just
 * a `class_exists()` check -- PHP's autoloader (Composer's PSR-4
 * mapping) does the actual file lookup, not this library.
 */
final class ConventionNamespaceResolver implements NamespaceResolver
{
    public function __construct(
        private readonly string $baseNamespace,
        private readonly string $classSuffix = 'Handler',
    ) {
    }

    public function resolve(string $rpcNamespace): ?string
    {
        $segments = array_map(
            static fn (string $segment): string => str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $segment))),
            explode('.', $rpcNamespace),
        );

        $className = trim($this->baseNamespace, '\\') . '\\' . implode('\\', $segments) . $this->classSuffix;

        return class_exists($className) ? $className : null;
    }
}
