<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Tests;

use Aicrion\JsonRpc\Kernel\RpcKernelBuilder;
use Aicrion\JsonRpc\Tests\Fixtures\AccountHandler;
use Aicrion\JsonRpc\Tests\Fixtures\AllowAllGate;
use Aicrion\JsonRpc\Tests\Fixtures\MathHandler;
use PHPUnit\Framework\TestCase;

final class RpcKernelTest extends TestCase
{
    public function testItInvokesAMethodExposedUnderItsNamespace(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => ['a' => 2, 'b' => 3],
            'id' => 1,
        ]);

        self::assertNull($response->error);
        self::assertSame(5, $response->result);
        self::assertSame(1, $response->id);
    }

    public function testItRespectsMethodAliases(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'math.multiply',
            'params' => [4, 5],
            'id' => 2,
        ]);

        self::assertSame(20, $response->result);
    }

    public function testUnexposedMethodsAreNotReachable(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'math.notExposed',
            'id' => 3,
        ]);

        self::assertNotNull($response->error);
        self::assertSame(-32601, $response->error->code);
    }

    public function testClassLevelProtectionAppliesToEveryMethodAutomatically(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new AccountHandler())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'account.balance',
            'params' => ['accountId' => 'abc'],
            'id' => 4,
        ]);

        self::assertNotNull($response->error);
        self::assertSame(-32001, $response->error->code);
    }

    public function testUnprotectedAttributeOptsOutOfClassLevelProtection(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new AccountHandler())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'account.ping',
            'id' => 5,
        ]);

        self::assertNull($response->error);
        self::assertSame('pong', $response->result);
    }

    public function testAuthorizationGateCanGrantAccessToProtectedMethods(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new AccountHandler())
            ->withAuthorizationGate(new AllowAllGate())
            ->build();

        $response = $kernel->dispatch([
            'jsonrpc' => '2.0',
            'method' => 'account.balance',
            'params' => ['accountId' => 'abc'],
            'id' => 6,
        ]);

        self::assertNull($response->error);
        self::assertSame(['accountId' => 'abc', 'balance' => 100], $response->result);
    }

    public function testMultipleNamespacesCanCoexistInTheSameKernel(): void
    {
        $kernel = (new RpcKernelBuilder())
            ->withHandlers([new MathHandler(), new AccountHandler()])
            ->withAuthorizationGate(new AllowAllGate())
            ->build();

        $sum = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 1], 'id' => 7]);
        $balance = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'account.balance', 'params' => ['accountId' => 'x'], 'id' => 8]);

        self::assertSame(2, $sum->result);
        self::assertSame('x', $balance->result['accountId']);
    }

    public function testUnknownMethodReturnsMethodNotFoundError(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $response = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'ghost.method', 'id' => 9]);

        self::assertSame(-32601, $response->error->code);
    }

    public function testMissingMethodFieldReturnsInvalidRequestError(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $response = $kernel->dispatch(['jsonrpc' => '2.0', 'id' => 10]);

        self::assertSame(-32600, $response->error->code);
    }

    public function testNotificationsWithoutIdReturnNullId(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $response = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2]]);

        self::assertNull($response->id);
        self::assertSame(3, $response->result);
    }

    public function testDispatchJsonProducesAValidJsonRpcEnvelope(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $raw = $kernel->dispatchJson(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => ['a' => 10, 'b' => 20],
            'id' => 11,
        ]));

        $decoded = json_decode($raw, true);

        self::assertSame('2.0', $decoded['jsonrpc']);
        self::assertSame(30, $decoded['result']);
        self::assertSame(11, $decoded['id']);
    }

    public function testDispatchBatchProcessesMultipleRequestsAndPreservesOrder(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $responses = $kernel->dispatchBatch([
            ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'math.multiply', 'params' => [3, 4], 'id' => 2],
        ]);

        self::assertCount(2, $responses);
        self::assertSame(3, $responses[0]->result);
        self::assertSame(12, $responses[1]->result);
    }

    public function testDispatchBatchOmitsNotificationsFromTheResponseList(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $responses = $kernel->dispatchBatch([
            ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2]],
            ['jsonrpc' => '2.0', 'method' => 'math.multiply', 'params' => [3, 4], 'id' => 9],
        ]);

        self::assertCount(1, $responses);
        self::assertSame(12, $responses[0]->result);
    }

    public function testEmptyBatchThrowsInvalidRequestError(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $this->expectException(\Aicrion\JsonRpc\Message\InvalidRequestPayloadException::class);
        $kernel->dispatchBatch([]);
    }

    public function testDispatchJsonAutoDetectsBatchPayloads(): void
    {
        $kernel = (new RpcKernelBuilder())->withHandler(new MathHandler())->build();

        $raw = $kernel->dispatchJson(json_encode([
            ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [5, 5], 'id' => 2],
        ]));

        $decoded = json_decode($raw, true);

        self::assertIsArray($decoded);
        self::assertSame(3, $decoded[0]['result']);
        self::assertSame(10, $decoded[1]['result']);
    }

    public function testListenersAreNotifiedBeforeAndAfterInvocation(): void
    {
        $listener = new \Aicrion\JsonRpc\Tests\Fixtures\RecordingListener();
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->withListener($listener)
            ->build();

        $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1]);

        self::assertSame(['before:math.add', 'after:math.add'], $listener->events);
    }

    public function testCustomMiddlewareStageCanBeInjectedIntoThePipeline(): void
    {
        $rateLimiter = new \Aicrion\JsonRpc\Pipeline\RateLimitStage(new \Aicrion\JsonRpc\Pipeline\LocalRateLimitStore(), maxCallsPerMethod: 1);
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->withMiddleware($rateLimiter)
            ->build();

        $first = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 1]);
        $second = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 2], 'id' => 2]);

        self::assertNull($first->error);
        self::assertNotNull($second->error);
        self::assertSame(-32002, $second->error->code);
    }

    public function testCacheableMethodReturnsMemoizedResultOnRepeatedCalls(): void
    {
        $clockHandler = new \Aicrion\JsonRpc\Tests\Fixtures\ClockHandler();
        $kernel = (new RpcKernelBuilder())
            ->withHandler($clockHandler)
            ->withCacheStore(new \Aicrion\JsonRpc\Cache\InMemoryCacheStore())
            ->build();

        $first = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.now', 'params' => ['zone' => 'utc'], 'id' => 1]);
        $second = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.now', 'params' => ['zone' => 'utc'], 'id' => 2]);

        self::assertSame(1, $first->result);
        self::assertSame(1, $second->result);
        self::assertSame(1, $clockHandler->callCount);
    }

    public function testCacheKeyVariesByArguments(): void
    {
        $clockHandler = new \Aicrion\JsonRpc\Tests\Fixtures\ClockHandler();
        $kernel = (new RpcKernelBuilder())
            ->withHandler($clockHandler)
            ->withCacheStore(new \Aicrion\JsonRpc\Cache\InMemoryCacheStore())
            ->build();

        $utc = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.now', 'params' => ['zone' => 'utc'], 'id' => 1]);
        $paris = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.now', 'params' => ['zone' => 'paris'], 'id' => 2]);

        self::assertSame(1, $utc->result);
        self::assertSame(2, $paris->result);
    }

    public function testNonCacheableMethodsAreNeverMemoized(): void
    {
        $clockHandler = new \Aicrion\JsonRpc\Tests\Fixtures\ClockHandler();
        $kernel = (new RpcKernelBuilder())
            ->withHandler($clockHandler)
            ->withCacheStore(new \Aicrion\JsonRpc\Cache\InMemoryCacheStore())
            ->build();

        $first = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.uncached', 'id' => 1]);
        $second = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'clock.uncached', 'id' => 2]);

        self::assertSame(1, $first->result);
        self::assertSame(2, $second->result);
    }

    public function testFileCacheStorePersistsAndReadsBackValues(): void
    {
        $dir = sys_get_temp_dir() . '/aicrion_jsonrpc_test_' . bin2hex(random_bytes(4));
        $store = new \Aicrion\JsonRpc\Cache\FileCacheStore($dir);

        $store->set('greeting', 'hello', 60);

        self::assertTrue($store->has('greeting'));
        self::assertSame('hello', $store->get('greeting'));

        $store->delete('greeting');
        self::assertFalse($store->has('greeting'));

        $store->clear();
        array_map('unlink', glob($dir . '/*.cache') ?: []);
        rmdir($dir);
    }

    public function testInMemoryCacheStoreRespectsTtlExpiry(): void
    {
        $store = new \Aicrion\JsonRpc\Cache\InMemoryCacheStore();
        $store->set('key', 'value', -1);

        self::assertFalse($store->has('key'));
        self::assertNull($store->get('key'));
    }

    public function testLocalRateLimitStoreEnforcesLimitWithinTheSameProcess(): void
    {
        $store = new \Aicrion\JsonRpc\Pipeline\LocalRateLimitStore();
        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->withMiddleware(new \Aicrion\JsonRpc\Pipeline\RateLimitStage($store, maxCallsPerMethod: 2))
            ->build();

        $r1 = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 1], 'id' => 1]);
        $r2 = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 1], 'id' => 2]);
        $r3 = $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 1], 'id' => 3]);

        self::assertNull($r1->error);
        self::assertNull($r2->error);
        self::assertNotNull($r3->error);
        self::assertSame(-32002, $r3->error->code);
    }

    public function testRateLimitStoreIsAPluggableAbstraction(): void
    {
        $fakeStore = new class implements \Aicrion\JsonRpc\Contract\RateLimitStore {
            public int $calls = 0;

            public function increment(string $key, int $windowSeconds): int
            {
                $this->calls++;

                return $this->calls;
            }
        };

        $kernel = (new RpcKernelBuilder())
            ->withHandler(new MathHandler())
            ->withMiddleware(new \Aicrion\JsonRpc\Pipeline\RateLimitStage($fakeStore, maxCallsPerMethod: 1))
            ->build();

        $kernel->dispatch(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [1, 1], 'id' => 1]);

        self::assertSame(1, $fakeStore->calls);
    }
}
