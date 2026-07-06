<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Attribute;

use Attribute;

/**
 * Exposes a public method as a callable RPC procedure.
 *
 * If $alias is omitted, the PHP method name is used as-is.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class RpcMethod
{
    public function __construct(
        public readonly ?string $alias = null,
    ) {
    }
}
