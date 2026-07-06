<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Attribute;

use Attribute;

/**
 * Explicitly opts a method out of a class-level #[Protected_] guard.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Unprotected
{
}
