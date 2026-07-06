<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Attribute;

use Attribute;

/**
 * Flags an RPC method (or an entire handler class) as requiring
 * authorization before invocation. When applied to a class, every
 * method inside inherits the protection unless it is explicitly
 * excluded with #[Unprotected].
 *
 * This removes the need to guard every single method by hand: the
 * Kernel's AuthorizationStage consults this attribute automatically.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Protected_
{
}
