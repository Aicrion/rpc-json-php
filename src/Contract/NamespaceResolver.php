<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

/**
 * Computes the fully-qualified handler class name responsible for a
 * given RPC namespace, without ever scanning a directory. Resolution
 * relies entirely on a naming convention plus the ordinary Composer
 * (PSR-4) autoloader -- exactly like `class_exists()` would resolve
 * any other class -- so no filesystem walk happens at all.
 */
interface NamespaceResolver
{
    /**
     * @return class-string|null the candidate handler class, or null
     *         if no class can be derived from this namespace
     */
    public function resolve(string $rpcNamespace): ?string;
}
