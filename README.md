# Aicrion\JsonRpc

A modern, attribute-driven JSON-RPC 2.0 server library for PHP 8.2+.

## Architecture

Unlike classic dispatcher-style RPC libraries, this library is built as a
**pipeline of independent stages** (Resolve -> Authorize -> Bind -> Invoke),
similar in spirit to HTTP middleware stacks:

```
Registry\HandlerRegistry   -- scans #[RpcHandler] classes via Reflection
Pipeline\ResolveMethodStage    -> looks up "namespace.method"
Pipeline\AuthorizationStage    -> enforces #[Protected_] automatically
Pipeline\BindParametersStage   -> maps positional/named params to args
Pipeline\InvokeHandlerStage    -> calls the real handler method
Kernel\RpcKernel               -- orchestrates the pipeline, builds RpcResponse
Kernel\RpcKernelBuilder        -- fluent, immutable builder for wiring handlers
```

## Feature 1 -- Automatic protected methods

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

The registry resolves protection once, at boot time, so the pipeline's
`AuthorizationStage` never needs per-method wiring.

## Feature 2 -- Namespaced handlers with their own paths

Every handler declares its own namespace via `#[RpcHandler('math')]`,
`#[RpcHandler('account')]`, etc. Methods become reachable as
`math.add`, `account.balance`, and any number of handlers/namespaces
can be registered on the same kernel:

```php
$kernel = (new RpcKernelBuilder())
    ->withHandlers([new MathHandler(), new AccountHandler()])
    ->withAuthorizationGate(new MyTokenGate())
    ->build();

$response = $kernel->dispatch([
    'jsonrpc' => '2.0',
    'method' => 'math.add',
    'params' => ['a' => 2, 'b' => 3],
    'id' => 1,
]);
```

## Installation

```bash
composer install
```

## Running tests

```bash
vendor/bin/phpunit
```

## Requirements

- PHP >= 8.2


## New features

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
$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(maxCallsPerMethod: 100))
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

Available cache stores:

| Store | Scope | Use case |
|---|---|---|
| `InMemoryCacheStore` | single request/process | tests, CLI scripts |
| `FileCacheStore` | single server, cross-request | no external cache service available |
| `ApcuCacheStore` | shared memory across PHP-FPM workers | single-server web apps |
| `RedisCacheStore` | distributed | multi-server / cluster deployments |
| `NullCacheStore` | none (default) | caching disabled |

Cache keys are derived from the method name and bound arguments via
`CacheKeyBuilder` (default: `DefaultCacheKeyBuilder`), also swappable
with `withCacheKeyBuilder()`.

### Rate limiting, local or distributed

`RateLimitStage` depends on the `RateLimitStore` abstraction, so the
exact same middleware works identically on one server or across a
cluster:

```php
use Aicrion\JsonRpc\Pipeline\RateLimitStage;
use Aicrion\JsonRpc\Pipeline\LocalRateLimitStore;   // single server
use Aicrion\JsonRpc\Pipeline\RedisRateLimitStore;   // distributed

$kernel = (new RpcKernelBuilder())
    ->withHandler(new MathHandler())
    ->withMiddleware(new RateLimitStage(new LocalRateLimitStore(), maxCallsPerMethod: 100, windowSeconds: 60))
    ->build();
```
