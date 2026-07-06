---
title: Aicrion JsonRpc
layout: default
---

# Aicrion\JsonRpc

A modern, attribute-driven JSON-RPC 2.0 server library for PHP 8.2+.

Built around a **pipeline of independent stages** instead of a single
monolithic dispatcher, this library lets you compose exactly the
behavior you need: namespaced handlers, automatic method protection,
batch requests, custom middleware, invocation listeners, and pluggable
caching / rate limiting -- all without touching the core.

[Get Started](#installation){: .btn } [View on GitHub](https://github.com/aicrion/json-rpc){: .btn }

---

## Table of contents

1. [Installation](#installation)
2. [Core concepts](#core-concepts)
3. [Quick start](#quick-start)
4. [Defining handlers](#defining-handlers)
5. [Namespaces](#namespaces)
6. [Auto-discovery and lazy loading](#auto-discovery-and-lazy-loading)
7. [Automatic method protection](#automatic-method-protection)
8. [Authorization gates](#authorization-gates)
9. [Parameter binding](#parameter-binding)
10. [Batch requests](#batch-requests)
11. [Custom middleware](#custom-middleware)
12. [Invocation listeners](#invocation-listeners)
13. [Caching](#caching)
14. [Rate limiting](#rate-limiting)
15. [Error handling](#error-handling)
16. [Full HTTP example](#full-http-example)
17. [Testing](#testing)
18. [API reference](#api-reference)

---

## Installation

Requires **PHP 8.2 or higher**.

```bash
composer require aicrion/json-rpc
```

Or, if you received the library as a standalone archive:

```bash
unzip aicrion-jsonrpc.zip
cd aicrion-jsonrpc
composer install
```

Run the test suite to confirm everything works on your machine:

```bash
composer test
```

---

## Core concepts

Aicrion\JsonRpc processes every request through a fixed pipeline of
**stages**. Each stage does exactly one job and passes control to the
next:

```
ResolveMethodStage -> AuthorizationStage -> [your middleware] -> BindParametersStage -> CachingStage -> NotifyListenersStage -> InvokeHandlerStage
```

| Concept | Description |
|---|---|
| `RpcHandler` (attribute) | Declares a class as a handler group bound to a namespace |
| `RpcMethod` (attribute) | Exposes a public method as a callable RPC procedure |
| `Protected_` / `Unprotected` (attributes) | Control which methods require authorization |
| `Cacheable` (attribute) | Marks a method's result as cacheable |
| `HandlerRegistry` | Scans handlers via Reflection and builds the method map |
| `RpcKernel` | Runs the pipeline and turns requests into responses |
| `RpcKernelBuilder` | Fluent, immutable way to configure and build a kernel |

---

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Kernel\RpcKernelBuilder;

#[RpcHandler('math')]
final class MathHandler
{
    #[RpcMethod]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->build();

$response = $kernel->dispatchJson(json_encode([
    'jsonrpc' => '2.0',
    'method' => 'math.add',
    'params' => ['a' => 2, 'b' => 3],
    'id' => 1,
]));

echo $response;
// {"jsonrpc":"2.0","id":1,"result":5}
```

---

## Defining handlers

A handler is any plain PHP class annotated with `#[RpcHandler]`. Every
public method annotated with `#[RpcMethod]` becomes callable through
the kernel; methods without this attribute stay private to PHP and are
**never reachable via RPC**, even if declared `public`.

```php
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

#[RpcHandler('users')]
final class UserHandler
{
    #[RpcMethod]
    public function find(int $id): array
    {
        return ['id' => $id, 'name' => 'Hadi'];
    }

    #[RpcMethod(alias: 'list')]
    public function findAll(): array
    {
        return [/* ... */];
    }

    // Not exposed: no #[RpcMethod] attribute.
    public function internalHelper(): void
    {
    }
}
```

Use the `alias` argument of `#[RpcMethod]` when the public RPC name
should differ from the PHP method name (e.g. `list` instead of
`findAll`).

---

## Namespaces

Every handler declares its own namespace through the first argument of
`#[RpcHandler]`. This namespace becomes the prefix of every exposed
method, so multiple handlers can be registered on the same kernel
without colliding:

```php
#[RpcHandler('math')]
final class MathHandler { /* math.add, math.multiply, ... */ }

#[RpcHandler('account')]
final class AccountHandler { /* account.balance, account.transfer, ... */ }

$kernel = (new RpcKernelBuilder())
    ->withHandlers([new MathHandler(), new AccountHandler()])
    ->build();
```

Registering the same qualified name twice (e.g. two handlers both
exposing `math.add`) throws
`InvalidHandlerDefinitionException::duplicateMethod()` at build time,
so collisions are caught immediately instead of silently overwriting
each other.


---

## Auto-discovery and lazy loading

Manually `new`-ing every handler and passing it to `withHandler()`
does not scale once you have dozens of handler classes -- and it wastes
memory/CPU instantiating handlers that a given request never calls.
Aicrion\JsonRpc solves both problems:

- **`withHandlerClass()`** registers a handler by class name only.
- **`withDiscoveredHandlers()`** scans an entire directory (recursively)
  for classes carrying `#[RpcHandler]`, following a PSR-4
  directory-to-namespace mapping -- similar to how Composer's own
  autoloader resolves class names from file paths.

In both cases, **construction is deferred**: only `ReflectionClass` is
used to read attributes during scanning/registration. The real `new`
call happens exactly once, the first time one of the handler's methods
is actually invoked, and the resulting instance is memoized for the
rest of the kernel's lifetime.

```php
use Aicrion\JsonRpc\Kernel\RpcKernelBuilder;

$kernel = (new RpcKernelBuilder())
    // Scans src/Handler recursively, maps files to the App\Handler namespace.
    ->withDiscoveredHandlers(__DIR__ . '/src/Handler', 'App\\Handler')
    ->build();

// MathHandler, AccountHandler, etc. are all registered here, but none
// of their constructors have run yet.

$kernel->dispatch([
    'jsonrpc' => '2.0',
    'method' => 'math.add',
    'params' => [1, 2],
    'id' => 1,
]);
// Only *now* is `new App\Handler\MathHandler()` actually called.
// AccountHandler, and every other discovered-but-unused handler,
// is still never instantiated.
```

### Injecting dependencies into discovered handlers

By default, discovered/lazy classes are built with a bare
`new $class()` via `DefaultHandlerFactory`. If a handler needs
constructor dependencies (a database connection, a logger, a
repository...), supply a `HandlerFactory` -- most commonly one backed
by your PSR-11 container:

```php
use Aicrion\JsonRpc\Registry\ContainerHandlerFactory;

$kernel = (new RpcKernelBuilder())
    ->withDiscoveredHandlers(__DIR__ . '/src/Handler', 'App\\Handler', new ContainerHandlerFactory($container))
    ->build();
```

`withHandlerClass()` accepts the same optional factory argument for a
single class:

```php
$kernel = (new RpcKernelBuilder())
    ->withHandlerClass(App\Handler\ReportHandler::class, new ContainerHandlerFactory($container))
    ->build();
```

### Mixing eager and lazy registration

`withHandler()` (eager), `withHandlerClass()` (lazy, single class),
and `withDiscoveredHandlers()` (lazy, whole directory) can all be
combined freely on the same builder -- the registry treats them
uniformly once registered:

```php
$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())                              // eager, already built
    ->withHandlerClass(AccountHandler::class)                      // lazy, single class
    ->withDiscoveredHandlers(__DIR__ . '/src/Reports', 'App\\Reports') // lazy, whole directory
    ->build();
```

### How directory scanning maps to class names

`discoverPath($directory, $namespace)` walks every `.php` file under
`$directory` recursively and rebuilds the fully-qualified class name by
appending the file's relative path (with `/` replaced by `\`, and the
`.php` extension stripped) to `$namespace` -- exactly the PSR-4
convention most PHP projects (and Composer's autoloader) already
follow:

```
src/Handler/MathHandler.php          -> App\Handler\MathHandler
src/Handler/Billing/InvoiceHandler.php -> App\Handler\Billing\InvoiceHandler
```

Files that do not resolve to an existing class, or whose class lacks
`#[RpcHandler]`, are silently skipped -- no exception is thrown for
ordinary, non-handler PHP files living in the same directory.

---

## Automatic method protection

Instead of guarding every method individually, apply `#[Protected_]`
**once, on the class**. Every method inside inherits the protection
automatically:

```php
use Aicrion\JsonRpc\Attribute\Protected_;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;
use Aicrion\JsonRpc\Attribute\Unprotected;

#[RpcHandler('account')]
#[Protected_]
final class AccountHandler
{
    #[RpcMethod]
    public function balance(string $accountId): array
    {
        // Protected automatically -- no per-method attribute needed.
    }

    #[RpcMethod]
    #[Unprotected]
    public function ping(): string
    {
        // Explicitly opted out of the class-level guard.
        return 'pong';
    }
}
```

You can also protect a single method inside an otherwise unprotected
class by placing `#[Protected_]` directly on that method.

Protection is resolved **once**, when `HandlerRegistry` scans the
class, and stored on the corresponding `MethodDescriptor`. The
`AuthorizationStage` simply reads that flag at request time -- there is
no manual wiring anywhere else.

---

## Authorization gates

Protection alone only flags *which* methods need authorization; *how*
that authorization is checked is delegated to an `AuthorizationGate`
implementation you provide:

```php
use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Registry\MethodDescriptor;

final class BearerTokenGate implements AuthorizationGate
{
    public function __construct(private readonly string $expectedToken) {}

    public function isAuthorized(MethodDescriptor $descriptor, array $params): bool
    {
        $token = $params['_token'] ?? null;

        return $token === $this->expectedToken;
    }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new AccountHandler())
    ->withAuthorizationGate(new BearerTokenGate('secret-token'))
    ->build();
```

If no gate is supplied, `NullAuthorizationGate` is used by default,
which **denies every protected method**. This is an intentional
fail-closed default: protected methods stay inaccessible until you
consciously wire up real authorization logic.

Unauthorized calls fail with error code `-32001`.

---

## Parameter binding

Params may be sent either **positionally** (a JSON array) or by
**name** (a JSON object) -- exactly like the JSON-RPC 2.0 spec allows:

```json
{"jsonrpc": "2.0", "method": "math.add", "params": [2, 3], "id": 1}
{"jsonrpc": "2.0", "method": "math.add", "params": {"a": 2, "b": 3}, "id": 1}
```

Both are bound onto the handler method's real signature by
`DefaultParameterBinder`, using reflection. Parameters with declared
default values fall back to those defaults when omitted; nullable
parameters fall back to `null`.

You can swap the binder entirely:

```php
use Aicrion\JsonRpc\Contract\ParameterBinder;

final class StrictParameterBinder implements ParameterBinder { /* ... */ }

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withParameterBinder(new StrictParameterBinder())
    ->build();
```

---

## Batch requests

Fully compliant with the JSON-RPC 2.0 batch specification: send an
array of request objects and get back an array of responses, with
notifications (requests lacking an `id`) silently executed but omitted
from the response list.

```php
$responses = $kernel->dispatchBatch([
    ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1],
    ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [5, 5]], // notification
]);
// $responses contains only the response for id=1
```

`dispatchJson()` auto-detects whether the decoded body is a single
object or a batch array and replies with the matching shape:

```php
$raw = $kernel->dispatchJson($request->getBody());
// returns a JSON object for a single request, or a JSON array for a batch
```

An empty batch (`[]`) throws `InvalidRequestPayloadException` with
code `-32600`, per spec.

---

## Custom middleware

Any class implementing the `Stage` interface can be spliced into the
pipeline between authorization and parameter binding, without touching
the kernel's internals:

```php
use Aicrion\JsonRpc\Message\RpcRequest;
use Aicrion\JsonRpc\Pipeline\PipelineContext;
use Aicrion\JsonRpc\Pipeline\Stage;

final class RequestLoggingStage implements Stage
{
    public function handle(RpcRequest $request, PipelineContext $context, callable $next): mixed
    {
        error_log('Calling ' . $request->method);

        return $next($request, $context);
    }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RequestLoggingStage())
    ->build();
```

Multiple `withMiddleware()` calls stack in the order they were added.

---

## Invocation listeners

For simpler cross-cutting concerns (metrics, audit trails) that do not
need to intercept the pipeline, implement `RpcListener` instead:

```php
use Aicrion\JsonRpc\Contract\RpcListener;
use Aicrion\JsonRpc\Registry\MethodDescriptor;
use Throwable;

final class MetricsListener implements RpcListener
{
    public function beforeInvoke(MethodDescriptor $descriptor, array $arguments): void
    {
        // start a timer, increment a counter, etc.
    }

    public function afterInvoke(MethodDescriptor $descriptor, mixed $result): void
    {
        // record success, stop the timer, etc.
    }

    public function onFailure(MethodDescriptor $descriptor, Throwable $exception): void
    {
        // record the failure
    }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withListener(new MetricsListener())
    ->build();
```

Listeners are notified around `InvokeHandlerStage` only, after caching
has already short-circuited (a cache hit does **not** trigger
listeners, since the handler itself was never invoked).

---

## Caching

Mark any method `#[Cacheable]` to memoize its result:

```php
use Aicrion\JsonRpc\Attribute\Cacheable;
use Aicrion\JsonRpc\Attribute\RpcHandler;
use Aicrion\JsonRpc\Attribute\RpcMethod;

#[RpcHandler('weather')]
final class WeatherHandler
{
    #[RpcMethod]
    #[Cacheable(ttlSeconds: 300)]
    public function forecast(string $city): array
    {
        // expensive external API call
    }
}
```

Choose any `CacheStore` that fits your deployment:

```php
use Aicrion\JsonRpc\Cache\InMemoryCacheStore;
use Aicrion\JsonRpc\Cache\FileCacheStore;
use Aicrion\JsonRpc\Cache\ApcuCacheStore;
use Aicrion\JsonRpc\Cache\RedisCacheStore;

$kernel = (new RpcKernelBuilder())
    ->withHandler(new WeatherHandler())
    ->withCacheStore(new RedisCacheStore($redisClient))
    ->build();
```

| Store | Scope | Best for |
|---|---|---|
| `InMemoryCacheStore` | single request/process | tests, CLI tools |
| `FileCacheStore` | single server, cross-request | no external cache service |
| `ApcuCacheStore` | shared across PHP-FPM workers | single-server web apps |
| `RedisCacheStore` | distributed | multi-server / cluster deployments |
| `NullCacheStore` | disabled (default) | no caching |

The cache key is computed from the method's qualified name and its
**bound arguments** via `CacheKeyBuilder`. Swap `DefaultCacheKeyBuilder`
with your own implementation if you need extra granularity (per-tenant,
per-user, per-API-version keys, etc.):

```php
$kernel = (new RpcKernelBuilder())
    ->withHandler(new WeatherHandler())
    ->withCacheStore(new RedisCacheStore($redisClient))
    ->withCacheKeyBuilder(new TenantAwareCacheKeyBuilder($tenantId))
    ->build();
```

Caching is checked **after** parameter binding but **before**
listener notification and invocation, so a cache hit skips both the
real handler call and any registered `RpcListener`.

---

## Rate limiting

`RateLimitStage` is a ready-made middleware built on the
`RateLimitStore` abstraction, so the exact same code works locally or
across a cluster:

```php
use Aicrion\JsonRpc\Pipeline\RateLimitStage;
use Aicrion\JsonRpc\Pipeline\LocalRateLimitStore;
use Aicrion\JsonRpc\Pipeline\RedisRateLimitStore;

// Single server:
$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(new LocalRateLimitStore(), maxCallsPerMethod: 100, windowSeconds: 60))
    ->build();

// Distributed, enforced identically across every node:
$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(new RedisRateLimitStore($redisClient), maxCallsPerMethod: 100, windowSeconds: 60))
    ->build();
```

Exceeding the limit fails with error code `-32002`.

---

## Error handling

Every exception this library throws extends `JsonRpcException` and
carries a JSON-RPC-compliant numeric code. The kernel automatically
converts any caught `JsonRpcException` into a proper error envelope --
you never need a try/catch around `dispatch()`.

| Code | Meaning |
|---|---|
| `-32700` | Parse error (malformed JSON) |
| `-32600` | Invalid request (missing/invalid method, id, or empty batch) |
| `-32601` | Method not found |
| `-32602` | Invalid params |
| `-32603` | Internal error (duplicate/missing handler registration) |
| `-32000` | Handler threw an uncaught exception |
| `-32001` | Unauthorized (protected method, gate denied access) |
| `-32002` | Rate limit exceeded |

Handler exceptions are wrapped in `MethodInvocationException`, which
exposes the original `Throwable` via its public `$cause` property for
logging purposes, while the client only ever sees a generic
"execution failed" message -- no internal details leak over the wire.

---

## Full HTTP example

A minimal `public/index.php` front controller for any PHP web server:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Aicrion\JsonRpc\Kernel\RpcKernelBuilder;

$kernel = (new RpcKernelBuilder())
    ->withHandlers([
        new MathHandler(),
        new AccountHandler(),
    ])
    ->build();

header('Content-Type: application/json');
echo $kernel->dispatchJson(file_get_contents('php://input'));
```

---

## Testing

The library ships with a full PHPUnit suite covering every feature
described above.

```bash
composer install
composer test
```

Run a single test:

```bash
composer test -- --filter testCacheableMethodReturnsMemoizedResultOnRepeatedCalls
```

Generate an HTML coverage report (requires Xdebug or PCOV):

```bash
composer test:coverage
```

---

## API reference

### Attributes (`Aicrion\JsonRpc\Attribute`)

- `RpcHandler(string $namespace, bool $protectedByDefault = false)`
- `RpcMethod(?string $alias = null)`
- `Protected_`
- `Unprotected`
- `Cacheable(int $ttlSeconds = 60)`

### Kernel (`Aicrion\JsonRpc\Kernel`)

- `RpcKernelBuilder::withHandler(object)` -- eager registration
- `RpcKernelBuilder::withHandlers(array)` -- eager registration, multiple
- `RpcKernelBuilder::withHandlerClass(string, ?HandlerFactory)` -- lazy, single class
- `RpcKernelBuilder::withDiscoveredHandlers(string $directory, string $namespace, ?HandlerFactory)` -- lazy, whole directory
- `RpcKernelBuilder::withAuthorizationGate(AuthorizationGate)`
- `RpcKernelBuilder::withParameterBinder(ParameterBinder)`
- `RpcKernelBuilder::withMiddleware(Stage)`
- `RpcKernelBuilder::withListener(RpcListener)`
- `RpcKernelBuilder::withCacheStore(CacheStore)`
- `RpcKernelBuilder::withCacheKeyBuilder(CacheKeyBuilder)`
- `RpcKernelBuilder::build(): RpcKernel`
- `RpcKernel::dispatch(array): RpcResponse`
- `RpcKernel::dispatchBatch(array): list<RpcResponse>`
- `RpcKernel::dispatchJson(string): string`

### Contracts (`Aicrion\JsonRpc\Contract`)

- `AuthorizationGate::isAuthorized(MethodDescriptor, array): bool`
- `ParameterBinder::bind(ReflectionMethod, array): array`
- `RpcListener::beforeInvoke / afterInvoke / onFailure`
- `CacheStore::get / has / set / delete / clear`
- `CacheKeyBuilder::build(MethodDescriptor, array): string`
- `RateLimitStore::increment(string, int): int`
- `HandlerFactory::create(string $handlerClass): object`

### Registry (`Aicrion\JsonRpc\Registry`)

- `HandlerRegistry::register(object)` -- eager
- `HandlerRegistry::registerClass(string, ?HandlerFactory)` -- lazy, single class
- `HandlerRegistry::discoverPath(string $directory, string $namespace, ?HandlerFactory)` -- lazy, whole directory
- `HandlerRegistry::find(string): ?MethodDescriptor`
- `LazyHandler::resolve(): object` -- builds and memoizes the instance on first call
- `LazyHandler::isResolved(): bool`
- `DefaultHandlerFactory` -- plain `new $class()`
- `ContainerHandlerFactory` -- resolves via any PSR-11 container
- `InstanceHandlerFactory` -- wraps an already-built instance

### Built-in implementations

- Cache: `InMemoryCacheStore`, `FileCacheStore`, `ApcuCacheStore`, `RedisCacheStore`, `NullCacheStore`
- Rate limiting: `LocalRateLimitStore`, `RedisRateLimitStore`
- Pipeline stages: `ResolveMethodStage`, `AuthorizationStage`, `BindParametersStage`, `CachingStage`, `NotifyListenersStage`, `InvokeHandlerStage`, `RateLimitStage`

---

<p align="center"><sub>Aicrion\JsonRpc &middot; MIT License &middot; PHP 8.2+</sub></p>
