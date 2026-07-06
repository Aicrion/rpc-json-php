<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Registry\MethodDescriptor;

/**
 * Default gate used when the Kernel is built without an explicit
 * AuthorizationGate. Denies every protected method, forcing
 * applications to consciously plug in real authorization logic.
 */
final class NullAuthorizationGate implements AuthorizationGate
{
    public function isAuthorized(MethodDescriptor $descriptor, array $params): bool
    {
        return false;
    }
}
