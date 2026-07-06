<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Message;

/**
 * Immutable value object representing a single JSON-RPC 2.0 request.
 */
final class RpcRequest
{
    /**
     * @param array<string, mixed>|list<mixed> $params
     */
    private function __construct(
        public readonly string $method,
        public readonly array $params,
        public readonly string|int|null $id,
        public readonly bool $isNotification,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromDecodedPayload(array $payload): self
    {
        $method = $payload['method'] ?? null;

        if (!is_string($method) || $method === '') {
            throw InvalidRequestPayloadException::missingMethod();
        }

        $params = $payload['params'] ?? [];

        if (!is_array($params)) {
            throw InvalidRequestPayloadException::invalidParams();
        }

        $hasId = array_key_exists('id', $payload);
        $id = $hasId ? $payload['id'] : null;

        if ($id !== null && !is_string($id) && !is_int($id)) {
            throw InvalidRequestPayloadException::invalidId();
        }

        return new self($method, $params, $id, !$hasId);
    }
}
