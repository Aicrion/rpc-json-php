<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Registry\MethodDescriptor;

/**
 * Mutable bag of state that travels alongside a request through the
 * pipeline stages (resolved descriptor, bound arguments, auth flag...).
 */
final class PipelineContext
{
    public ?MethodDescriptor $descriptor = null;

    /** @var list<mixed> */
    public array $boundArguments = [];
}
