<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Contract;

use Aicrion\JsonRpc\Registry\MethodDescriptor;

/**
 * Decides, at request time, whether the caller is allowed to invoke a
 * method that has been flagged protected via #[Protected_]. Applications
 * implement this to plug in tokens, sessions, API keys, etc.
 */
interface AuthorizationGate
{
    /**
     * @param array<string, mixed>|list<mixed> $params
     */
    public function isAuthorized(MethodDescriptor $descriptor, array $params): bool;
}
