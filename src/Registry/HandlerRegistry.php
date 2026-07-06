<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Registry;

use Aicrion\JsonRpc\Attribute\Cacheable;
use Aicrion\JsonRpc\Attribute\Protected_;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Attribute\Unprotected;
use Aicrion\JsonRpc\Contract\HandlerFactory;
use Aicrion\JsonRpc\Contract\NamespaceResolver;
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
 * Four ways to feed the registry:
 *   - register(object)                 eager instance you already built
 *   - registerClass(string)            lazy, class name only
 *   - discoverPath(dir, namespace)     lazy, scans a directory once
 *     and optionally persists the resulting metadata to a cache file
 *     so subsequent process boot-ups never touch the filesystem walk
 *     again.
 *   - withResolver(NamespaceResolver)  no scan at all -- the handler
 *     class for an unknown "namespace.method" is derived on demand,
 *     the very first time that method is requested, purely from a
 *     naming convention (or an explicit map) resolved through PHP's
 *     ordinary autoloader.
 */
final class HandlerRegistry
{
    /** @var array<string, MethodDescriptor> */
    private array $methods = [];

    /** @var array<class-string, true> */
    private array $registeredClasses = [];

    /** @var list<array{resolver: NamespaceResolver, factory: HandlerFactory}> */
    private array $resolvers = [];

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
     * If $cacheFile is provided and already exists, the filesystem
     * walk is skipped entirely and the method map is rebuilt straight
     * from the cached metadata -- ideal for PHP-FPM/CLI processes that
     * would otherwise re-scan the same directory on every boot. Pass
     * a writable path the very first time (or after handlers change)
     * to populate/refresh it; delete the file to force a re-scan.
     */
    public function discoverPath(
        string $directory,
        string $namespacePrefix,
        ?HandlerFactory $factory = null,
        ?string $cacheFile = null,
    ): void {
        $factory ??= new DefaultHandlerFactory();

        if ($cacheFile !== null && is_file($cacheFile)) {
            $this->loadFromCacheFile($cacheFile, $factory);

            return;
        }

        $namespacePrefix = trim($namespacePrefix, '\\');
        $discovered = [];

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
            $discovered[] = $className;
        }

        if ($cacheFile !== null) {
            $this->writeCacheFile($cacheFile, $discovered);
        }
    }

    /**
     * Enables resolve-on-demand mode: no directory is ever scanned.
     * When find() is asked for a qualified method it does not already
     * know about, it strips the last ".segment" to get the RPC
     * namespace, hands that to $resolver to compute a candidate class
     * name (typically via a naming convention, e.g. "math" ->
     * "App\Handler\MathHandler"), and -- if that class exists and
     * is annotated with #[RpcHandler] -- registers it on the spot,
     * lazily, exactly as registerClass() would.
     *
     * Multiple resolvers can be added; they are tried in registration
     * order until one produces an existing, valid handler class.
     */
    public function withResolver(NamespaceResolver $resolver, ?HandlerFactory $factory = null): void
    {
        $this->resolvers[] = ['resolver' => $resolver, 'factory' => $factory ?? new DefaultHandlerFactory()];
    }

    public function find(string $qualifiedName): ?MethodDescriptor
    {
        if (isset($this->methods[$qualifiedName])) {
            return $this->methods[$qualifiedName];
        }

        return $this->resolveOnDemand($qualifiedName);
    }

    /**
     * @return list<string>
     */
    public function registeredMethods(): array
    {
        return array_keys($this->methods);
    }

    private function resolveOnDemand(string $qualifiedName): ?MethodDescriptor
    {
        $lastDot = strrpos($qualifiedName, '.');

        if ($lastDot === false || $this->resolvers === []) {
            return null;
        }

        $rpcNamespace = substr($qualifiedName, 0, $lastDot);

        foreach ($this->resolvers as $entry) {
            $className = $entry['resolver']->resolve($rpcNamespace);

            if ($className === null || isset($this->registeredClasses[$className])) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->getAttributes(RpcHandler::class) === []) {
                continue;
            }

            $this->registerFromClass($className, $entry['factory']);

            if (isset($this->methods[$qualifiedName])) {
                return $this->methods[$qualifiedName];
            }
        }

        return null;
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
     * @param list<class-string> $discoveredClasses
     */
    private function writeCacheFile(string $cacheFile, array $discoveredClasses): void
    {
        $directory = dirname($cacheFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $cacheFile,
            "<?php\n\nreturn " . var_export($discoveredClasses, true) . ";\n",
        );
    }

    private function loadFromCacheFile(string $cacheFile, HandlerFactory $factory): void
    {
        /** @var list<class-string> $classes */
        $classes = require $cacheFile;

        foreach ($classes as $className) {
            if (class_exists($className)) {
                $this->registerFromClass($className, $factory);
            }
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
