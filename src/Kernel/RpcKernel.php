<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Kernel;

use Aicrion\JsonRpc\Cache\DefaultCacheKeyBuilder;
use Aicrion\JsonRpc\Cache\NullCacheStore;
use Aicrion\JsonRpc\Contract\AuthorizationGate;
use Aicrion\JsonRpc\Contract\CacheKeyBuilder;
use Aicrion\JsonRpc\Contract\CacheStore;
use Aicrion\JsonRpc\Contract\ParameterBinder;
use Aicrion\JsonRpc\Contract\RpcListener;
use Aicrion\JsonRpc\Exception\JsonRpcException;
use Aicrion\JsonRpc\Message\InvalidRequestPayloadException;
use Aicrion\JsonRpc\Message\RpcBatchRequest;
use Aicrion\JsonRpc\Message\RpcError;
use Aicrion\JsonRpc\Message\RpcRequest;
use Aicrion\JsonRpc\Message\RpcResponse;
use Aicrion\JsonRpc\Pipeline\AuthorizationStage;
use Aicrion\JsonRpc\Pipeline\BindParametersStage;
use Aicrion\JsonRpc\Pipeline\CachingStage;
use Aicrion\JsonRpc\Pipeline\DefaultParameterBinder;
use Aicrion\JsonRpc\Pipeline\InvokeHandlerStage;
use Aicrion\JsonRpc\Pipeline\NotifyListenersStage;
use Aicrion\JsonRpc\Pipeline\NullAuthorizationGate;
use Aicrion\JsonRpc\Pipeline\PipelineContext;
use Aicrion\JsonRpc\Pipeline\ResolveMethodStage;
use Aicrion\JsonRpc\Pipeline\Stage;
use Aicrion\JsonRpc\Registry\HandlerRegistry;

/**
 * Entry point of the library. Builds a pipeline of stages
 * (resolve -> authorize -> [custom middleware] -> bind params ->
 * notify listeners -> invoke) and exposes dispatch()/dispatchBatch()
 * to turn decoded JSON-RPC payloads into RpcResponse objects,
 * catching any JsonRpcException into a proper error envelope.
 */
final class RpcKernel
{
    /** @var list<Stage> */
    private readonly array $pipeline;

    /**
     * @param list<Stage> $middleware extra stages spliced in after authorization
     * @param list<RpcListener> $listeners
     */
    public function __construct(
        private readonly HandlerRegistry $registry,
        ?AuthorizationGate $authorizationGate = null,
        ?ParameterBinder $parameterBinder = null,
        array $middleware = [],
        array $listeners = [],
        ?CacheStore $cacheStore = null,
        ?CacheKeyBuilder $cacheKeyBuilder = null,
    ) {
        $this->pipeline = [
            new ResolveMethodStage($this->registry),
            new AuthorizationStage($authorizationGate ?? new NullAuthorizationGate()),
            ...$middleware,
            new BindParametersStage($parameterBinder ?? new DefaultParameterBinder()),
            new CachingStage($cacheStore ?? new NullCacheStore(), $cacheKeyBuilder ?? new DefaultCacheKeyBuilder()),
            new NotifyListenersStage($listeners),
            new InvokeHandlerStage(),
        ];
    }

    /**
     * @param array<string, mixed> $decodedPayload already json_decode()'d as an associative array
     */
    public function dispatch(array $decodedPayload): RpcResponse
    {
        try {
            $request = RpcRequest::fromDecodedPayload($decodedPayload);

            return $this->run($request);
        } catch (JsonRpcException $exception) {
            $id = array_key_exists('id', $decodedPayload) ? $decodedPayload['id'] : null;

            return RpcResponse::failure(
                is_string($id) || is_int($id) ? $id : null,
                new RpcError($exception->rpcCode, $exception->getMessage(), $exception->rpcData),
            );
        }
    }

    /**
     * Dispatches a JSON-RPC 2.0 batch (an array of request payloads).
     * Notifications (requests without "id") are executed but omitted
     * from the returned list, per the specification.
     *
     * @param list<mixed> $decodedPayloads
     * @return list<RpcResponse>
     */
    public function dispatchBatch(array $decodedPayloads): array
    {
        $batch = RpcBatchRequest::fromDecodedArray($decodedPayloads);
        $responses = [];

        foreach ($batch->payloads as $payload) {
            if (!is_array($payload)) {
                $responses[] = RpcResponse::failure(null, new RpcError(-32600, 'Each batch item must be an object.'));
                continue;
            }

            $isNotification = !array_key_exists('id', $payload);
            $response = $this->dispatch($payload);

            if (!$isNotification) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    /**
     * Accepts a raw JSON body, auto-detects whether it is a single
     * request or a batch, and returns the encoded JSON-RPC response
     * (an object for single requests, an array for batches).
     */
    public function dispatchJson(string $rawJson): string
    {
        $decoded = json_decode($rawJson, true);

        if (!is_array($decoded)) {
            $error = new RpcError(-32700, 'The request body is not valid JSON.');

            return json_encode(RpcResponse::failure(null, $error)->toArray()) ?: '{}';
        }

        if (array_is_list($decoded) && $decoded !== []) {
            $responses = $this->dispatchBatch($decoded);

            return json_encode(array_map(static fn (RpcResponse $r): array => $r->toArray(), $responses)) ?: '[]';
        }

        return json_encode($this->dispatch($decoded)->toArray()) ?: '{}';
    }

    private function run(RpcRequest $request): RpcResponse
    {
        $context = new PipelineContext();
        $pipeline = array_reverse($this->pipeline);

        $next = static fn (RpcRequest $req, PipelineContext $ctx): mixed => throw new \LogicException(
            'Pipeline exhausted without a terminal stage producing a result.',
        );

        foreach ($pipeline as $stage) {
            $currentNext = $next;
            $next = static fn (RpcRequest $req, PipelineContext $ctx): mixed => $stage->handle($req, $ctx, $currentNext);
        }

        $result = $next($request, $context);

        return RpcResponse::success($request->id, $result);
    }
}
