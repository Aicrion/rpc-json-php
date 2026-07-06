<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Registry\MethodDescriptor;

final class AllowAllGate implements AuthorizationGate
{
    public function isAuthorized(MethodDescriptor $descriptor, array $params): bool
    {
        return true;
    }
}
