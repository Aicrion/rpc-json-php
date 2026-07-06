<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Attribute\Cacheable;
use Aicrion\JsonRpc\Attribute\Protected_;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Attribute\Unprotected;
use Aicrion\JsonRpc\Exception\InvalidHandlerDefinitionException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Scans handler classes via Reflection and builds a flat map of
 * "namespace.method" => MethodDescriptor. This is the component that
 * makes automatic namespacing and blanket protection possible: no
 * manual wiring per method is required anywhere else in the library.
 */
final class HandlerRegistry
{
    /** @var array<string, MethodDescriptor> */
    private array $methods = [];

    /**
     * @param list<object> $handlers instantiated handler objects, each
     *        annotated with #[RpcHandler(namespace: ...)]
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(object $handler): void
    {
        $reflection = new ReflectionClass($handler);
        $handlerAttribute = $reflection->getAttributes(RpcHandler::class)[0] ?? null;

        if ($handlerAttribute === null) {
            throw InvalidHandlerDefinitionException::missingNamespaceAttribute($reflection->getName());
        }

        /** @var RpcHandler $rpcHandler */
        $rpcHandler = $handlerAttribute->newInstance();
        $classIsProtected = $this->hasAttribute($reflection, Protected_::class) || $rpcHandler->protectedByDefault;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodAttribute = $method->getAttributes(RpcMethod::class)[0] ?? null;

            if ($methodAttribute === null) {
                continue;
            }

            /** @var RpcMethod $rpcMethod */
            $rpcMethod = $methodAttribute->newInstance();
            $localName = $rpcMethod->alias ?? $method->getName();
            $qualifiedName = $rpcHandler->namespace . '.' . $localName;

            if (isset($this->methods[$qualifiedName])) {
                throw InvalidHandlerDefinitionException::duplicateMethod($qualifiedName);
            }

            $isProtected = $classIsProtected
                ? !$this->hasAttribute($method, Unprotected::class)
                : $this->hasAttribute($method, Protected_::class);

            $cacheableAttribute = $method->getAttributes(Cacheable::class)[0] ?? null;
            $cacheTtlSeconds = $cacheableAttribute !== null
                ? $cacheableAttribute->newInstance()->ttlSeconds
                : null;

            $this->methods[$qualifiedName] = new MethodDescriptor(
                qualifiedName: $qualifiedName,
                handlerInstance: $handler,
                handlerMethod: $method->getName(),
                isProtected: $isProtected,
                cacheTtlSeconds: $cacheTtlSeconds,
            );
        }
    }

    public function find(string $qualifiedName): ?MethodDescriptor
    {
        return $this->methods[$qualifiedName] ?? null;
    }

    /**
     * @return list<string>
     */
    public function registeredMethods(): array
    {
        return array_keys($this->methods);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $subject
     */
    private function hasAttribute(ReflectionClass|ReflectionMethod $subject, string $attributeClass): bool
    {
        return $subject->getAttributes($attributeClass) !== [];
    }
}
