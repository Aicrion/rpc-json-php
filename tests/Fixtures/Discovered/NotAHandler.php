<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures\Discovered;

/**
 * Deliberately lacks #[RpcHandler] to prove discoverPath() skips
 * ordinary classes without throwing.
 */
final class NotAHandler
{
    public function doSomething(): void
    {
    }
}
