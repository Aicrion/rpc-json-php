<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Attribute\Cacheable;
use Aicrion\JsonRpc\Attribute\Protected_;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Attribute\Unprotected;
use Aicrion\JsonRpc\Contract\HandlerFactory;
use Aicrion\JsonRpc\Exception\InvalidHandlerDefinitionException;
use FilesystemIterator;
use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Builds a flat map of "namespace.method" => MethodDescriptor without
 * ever eagerly instantiating a handler class. Registration only needs
 * a class *name* (via Reflection) to read its attributes; the actual
 * object is created lazily, on first invocation, through a
 * HandlerFactory wrapped in a LazyHandler.
 *
 * Three ways to feed the registry:
 *   - register(object)             eager instance you already built
 *   - registerClass(string)        lazy, class name only
 *   - discoverPath(dir, namespace) lazy, scans a directory for classes
 *     carrying #[RpcHandler], following a PSR-4 directory <-> namespace
 *     mapping -- no manual `new` calls anywhere.
 */
final class HandlerRegistry
{
    /** @var array<string, MethodDescriptor> */
    private array $methods = [];

    /** @var array<class-string, true> */
    private array $registeredClasses = [];

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

    /**
     * Registers an already-instantiated handler (eager path).
     */
    public function register(object $handler): void
    {
        $this->registerFromClass($handler::class, new InstanceHandlerFactory($handler));
    }

    /**
     * Registers a handler by class name only. Nothing is instantiated
     * until one of its methods is actually invoked.
     */
    public function registerClass(string $handlerClass, ?HandlerFactory $factory = null): void
    {
        $this->registerFromClass($handlerClass, $factory ?? new DefaultHandlerFactory());
    }

    /**
     * Recursively scans $directory for PHP files, maps each file path
     * to a fully-qualified class name using the given PSR-4
     * $namespacePrefix, and lazily registers every class annotated
     * with #[RpcHandler]. Classes are never instantiated during the
     * scan -- only Reflection is used to read attributes -- and actual
     * construction is deferred to first invocation via $factory.
     *
     * Example: discoverPath(__DIR__ . '/Handler', 'App\\Handler')
     * maps App/Handler/MathHandler.php to the class App\Handler\MathHandler.
     */
    public function discoverPath(string $directory, string $namespacePrefix, ?HandlerFactory $factory = null): void
    {
        $factory ??= new DefaultHandlerFactory();
        $namespacePrefix = trim($namespacePrefix, '\\');

        foreach ($this->phpFilesIn($directory) as $file) {
            $className = $this->classNameFromFile($file, $directory, $namespacePrefix);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            if ($reflection->getAttributes(RpcHandler::class) === []) {
                continue;
            }

            $this->registerFromClass($className, $factory);
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

    private function registerFromClass(string $handlerClass, HandlerFactory $factory): void
    {
        if (isset($this->registeredClasses[$handlerClass])) {
            return;
        }

        $this->registeredClasses[$handlerClass] = true;

        $reflection = new ReflectionClass($handlerClass);
        $handlerAttribute = $reflection->getAttributes(RpcHandler::class)[0] ?? null;

        if ($handlerAttribute === null) {
            throw InvalidHandlerDefinitionException::missingNamespaceAttribute($reflection->getName());
        }

        /** @var RpcHandler $rpcHandler */
        $rpcHandler = $handlerAttribute->newInstance();
        $classIsProtected = $this->hasAttribute($reflection, Protected_::class) || $rpcHandler->protectedByDefault;
        $lazyHandler = new LazyHandler($handlerClass, $factory);

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
                handler: $lazyHandler,
                handlerMethod: $method->getName(),
                isProtected: $isProtected,
                cacheTtlSeconds: $cacheTtlSeconds,
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function classNameFromFile(SplFileInfo $file, string $baseDirectory, string $namespacePrefix): ?string
    {
        $realBase = realpath($baseDirectory) ?: $baseDirectory;
        $realFile = $file->getRealPath() ?: $file->getPathname();

        $relative = ltrim(str_replace($realBase, '', $realFile), \DIRECTORY_SEPARATOR);
        $relative = substr($relative, 0, -4); // strip ".php"
        $relative = str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);

        if ($relative === '') {
            return null;
        }

        return $namespacePrefix . '\\' . $relative;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $subject
     */
    private function hasAttribute(ReflectionClass|ReflectionMethod $subject, string $attributeClass): bool
    {
        return $subject->getAttributes($attributeClass) !== [];
    }
}
