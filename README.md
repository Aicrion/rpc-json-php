# Aicrion\JsonRpc

A modern, attribute-driven JSON-RPC 2.0 server library for PHP 8.2+.

Full documentation (installation, every feature, API reference) lives in
[`docs/index.md`](docs/index.md) -- built to be published on GitHub Pages.
This README is a concise overview and quick-start reference.

## Architecture

Unlike classic dispatcher-style RPC libraries, this library is built as a
**pipeline of independent stages**, similar in spirit to HTTP middleware
stacks. Every request flows through the same fixed sequence:

```
Registry\HandlerRegistry          -- resolves handler metadata via Reflection,
                                      eagerly, lazily by class, by directory
                                      scan (optionally cached to disk), or
                                      fully on-demand via a NamespaceResolver
Registry\LazyHandler              -- defers `new $class()` until the handler
                                      is actually invoked, then memoizes it

Pipeline\ResolveMethodStage        -> looks up "namespace.method"
Pipeline\AuthorizationStage        -> enforces #[Protected_] automatically
[ ...custom middleware, e.g. RateLimitStage ]
Pipeline\BindParametersStage       -> maps positional/named params to args
Pipeline\CachingStage              -> short-circuits on a #[Cacheable] hit
Pipeline\NotifyListenersStage      -> before/after/failure hooks for RpcListener
Pipeline\InvokeHandlerStage        -> resolves the LazyHandler and calls it

Kernel\RpcKernel                  -- orchestrates the pipeline, builds RpcResponse
Kernel\RpcKernelBuilder           -- fluent, immutable builder for wiring everything
```

Every stage implements the same `Pipeline\Stage` interface, so the pipeline
can be extended (via `withMiddleware()`) without ever touching the kernel's
internals.

## Requirements

- PHP >= 8.2
- `psr/container` (installed automatically as a dependency; only required at
  runtime if you use `ContainerHandlerFactory`)

## Installation

```bash
composer install
```

## Running tests

```bash
composer test
```

Run a single test:

```bash
composer test -- --filter testNameHere
```

## Quick start

```php
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

echo $kernel->dispatchJson(json_encode([
    'jsonrpc' => '2.0',
    'method' => 'math.add',
    'params' => ['a' => 2, 'b' => 3],
    'id' => 1,
]));
// {"jsonrpc":"2.0","id":1,"result":5}
```

## Feature overview

### Namespaced handlers

Every handler declares its own namespace via `#[RpcHandler('math')]`,
`#[RpcHandler('account')]`, etc. Methods become reachable as `math.add`,
`account.balance`, and any number of handlers/namespaces can be registered
on the same kernel without colliding (a duplicate qualified name throws at
build time).

### Automatic protected methods

Instead of guarding each method by hand, annotate the whole handler class:

```php
#[RpcHandler('account')]
#[Protected_]
final class AccountHandler
{
    #[RpcMethod]
    public function balance(string $accountId): array { /* protected automatically */ }

    #[RpcMethod]
    #[Unprotected]
    public function ping(): string { /* explicitly opts out */ }
}
```

The registry resolves protection once, at registration time, so
`AuthorizationStage` never needs per-method wiring. The actual yes/no
decision is delegated to any `AuthorizationGate` you provide via
`withAuthorizationGate()` (defaults to fail-closed, denying every
protected method).

### Auto-discovery and lazy loading

No handler is ever instantiated until one of its methods is actually
invoked -- regardless of which registration strategy you use:

```php
$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())                                       // eager instance
    ->withHandlerClass(AccountHandler::class)                               // lazy, single class
    ->withDiscoveredHandlers(__DIR__ . '/src/Handler', 'App\\Handler')    // lazy, whole directory
    ->build();
```

Two ways to avoid the cost of scanning a directory on every request:

- **Cache the scan to disk**: pass a `cacheFile` to `withDiscoveredHandlers()`.
  The directory is scanned once, the resulting class list is persisted, and
  every subsequent `build()` skips the filesystem walk entirely.
- **Resolve on demand, never scan**: use `withResolvedHandlers()` with a
  `NamespaceResolver` (`ConventionNamespaceResolver` or `MapNamespaceResolver`).
  The handler class for a namespace is computed purely from a naming
  convention or explicit map, and verified via `class_exists()` the first
  time that namespace is actually requested.

| Strategy | Filesystem scan | Instantiation |
|---|---|---|
| `withHandler()` / `withHandlers()` | none | immediate (eager) |
| `withHandlerClass()` | none | on first invocation |
| `withDiscoveredHandlers()` | once per `build()` | on first invocation |
| `withDiscoveredHandlers(cacheFile: ...)` | once ever | on first invocation |
| `withResolvedHandlers()` | never | on first invocation |

Handlers needing constructor dependencies can be built through any PSR-11
container via `ContainerHandlerFactory`, passed to any of the lazy
registration methods above.

### Batch requests (JSON-RPC 2.0 spec compliant)

```php
$responses = $kernel->dispatchBatch([
    ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1],
    ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [5, 5]], // notification, no response
]);
// $responses contains only the answer to id=1
```

`dispatchJson()` auto-detects whether the raw body is a single request
object or a batch array and responds accordingly.

### Custom middleware stages

Plug any `Stage` implementation into the pipeline, right after
authorization and before parameter binding:

```php
use Aicrion\JsonRpc\Pipeline\RateLimitStage;
use Aicrion\JsonRpc\Pipeline\LocalRateLimitStore;

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(new LocalRateLimitStore(), maxCallsPerMethod: 100, windowSeconds: 60))
    ->build();
```

### Invocation listeners

Observe every call without touching the pipeline:

```php
final class LoggingListener implements RpcListener
{
    public function beforeInvoke(MethodDescriptor $d, array $args): void { /* ... */ }
    public function afterInvoke(MethodDescriptor $d, mixed $result): void { /* ... */ }
    public function onFailure(MethodDescriptor $d, Throwable $e): void { /* ... */ }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withListener(new LoggingListener())
    ->build();
```

Listeners fire around `InvokeHandlerStage` only -- a cache hit never
triggers them, since the real handler method was never called.

### Caching with pluggable backends

Annotate any method with `#[Cacheable]` to memoize its result. Pick any
`CacheStore` implementation that fits your deployment:

```php
use Aicrion\JsonRpc\Attribute\Cacheable;

#[RpcHandler('weather')]
final class WeatherHandler
{
    #[RpcMethod]
    #[Cacheable(ttlSeconds: 300)]
    public function forecast(string $city): array { /* ... */ }
}

$kernel = (new RpcKernelBuilder())
    ->withHandler(new WeatherHandler())
    ->withCacheStore(new InMemoryCacheStore())   // or FileCacheStore, ApcuCacheStore, RedisCacheStore
    ->build();
```

| Store | Scope | Use case |
|---|---|---|
| `InMemoryCacheStore` | single request/process | tests, CLI scripts |
| `FileCacheStore` | single server, cross-request | no external cache service available |
| `ApcuCacheStore` | shared memory across PHP-FPM workers | single-server web apps |
| `RedisCacheStore` | distributed | multi-server / cluster deployments |
| `NullCacheStore` | none (default) | caching disabled |

Cache keys are derived from the method name and bound arguments via
`CacheKeyBuilder` (default: `DefaultCacheKeyBuilder`), also swappable with
`withCacheKeyBuilder()`.

### Rate limiting, local or distributed

`RateLimitStage` depends on the `RateLimitStore` abstraction, so the exact
same middleware works identically on one server or across a cluster:

```php
use Aicrion\JsonRpc\Pipeline\RateLimitStage;
use Aicrion\JsonRpc\Pipeline\LocalRateLimitStore;   // single server
use Aicrion\JsonRpc\Pipeline\RedisRateLimitStore;   // distributed

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(new RedisRateLimitStore($redisClient), maxCallsPerMethod: 100, windowSeconds: 60))
    ->build();
```

### Returning custom errors from a handler

Throwing a plain exception inside a handler is always wrapped into a
generic `-32000` error, protecting clients from internal stack traces.
To return a specific code/message/data for an expected business failure,
throw `Exception\RpcErrorException` instead:

```php
use Aicrion\JsonRpc\Exception\RpcErrorException;

#[RpcMethod]
public function withdraw(float $amount): array
{
    if ($amount > 100) {
        throw new RpcErrorException('Insufficient funds', -32020, ['available' => 100]);
    }

    return ['withdrawn' => $amount];
}
```

This propagates untouched to the client, while any other, unexpected
exception still gets wrapped as a generic internal error. Reserve custom
codes below `-32000` to avoid colliding with the JSON-RPC 2.0 reserved
range and this library's own codes (`-32001` unauthorized, `-32002` rate
limited).

## Error codes

| Code | Meaning |
|---|---|
| `-32700` | Parse error (malformed JSON) |
| `-32600` | Invalid request (missing/invalid method, id, or empty batch) |
| `-32601` | Method not found |
| `-32602` | Invalid params |
| `-32603` | Internal error (duplicate/missing handler registration) |
| `-32000` | Handler threw an unexpected exception |
| `-32001` | Unauthorized (protected method, gate denied access) |
| `-32002` | Rate limit exceeded |

## Documentation and license

See [`docs/index.md`](docs/index.md) for the full guide, including a
complete API reference. Published under the MIT License.

## 📜 License

Created with ❤️ by Aicrion. Licensed under the [MIT License](LICENSE.md). Free to use, modify, and distribute!
