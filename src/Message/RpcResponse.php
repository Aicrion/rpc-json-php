<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Message;

/**
 * Immutable value object representing a JSON-RPC 2.0 response,
 * either a success result or an error envelope.
 */
final class RpcResponse
{
    private function __construct(
        public readonly string|int|null $id,
        public readonly mixed $result,
        public readonly ?RpcError $error,
    ) {
    }

    public static function success(string|int|null $id, mixed $result): self
    {
        return new self($id, $result, null);
    }

    public static function failure(string|int|null $id, RpcError $error): self
    {
        return new self($id, null, $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $envelope = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
        ];

        if ($this->error !== null) {
            $envelope['error'] = $this->error->toArray();
        } else {
            $envelope['result'] = $this->result;
        }

        return $envelope;
    }
}
