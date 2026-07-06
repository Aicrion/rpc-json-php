<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Message;

/**
 * Immutable JSON-RPC 2.0 error object.
 */
final class RpcError
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly ?array $data = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        return $payload;
    }
}
