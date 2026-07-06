<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests\Fixtures;

use Aicrion\JsonRpc\Contract\RpcListener;
use Aicrion\JsonRpc\Registry\MethodDescriptor;
use Throwable;

final class RecordingListener implements RpcListener
{
    /** @var list<string> */
    public array $events = [];

    public function beforeInvoke(MethodDescriptor $descriptor, array $arguments): void
    {
        $this->events[] = 'before:' . $descriptor->qualifiedName;
    }

    public function afterInvoke(MethodDescriptor $descriptor, mixed $result): void
    {
        $this->events[] = 'after:' . $descriptor->qualifiedName;
    }

    public function onFailure(MethodDescriptor $descriptor, Throwable $exception): void
    {
        $this->events[] = 'failure:' . $descriptor->qualifiedName;
    }
}
