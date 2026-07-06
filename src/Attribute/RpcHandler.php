<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Attribute;

use Attribute;

/**
 * Marks a class as an RPC handler group and binds it to a namespace segment.
 *
 * The namespace becomes the "path" prefix used to resolve method calls,
 * e.g. #[RpcHandler('math')] exposes methods as "math.add", "math.sub", ...
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class RpcHandler
{
    public function __construct(
        public readonly string $namespace,
        public readonly bool $protectedByDefault = false,
    ) {
    }
}
