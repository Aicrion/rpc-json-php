<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Message;

/**
 * Represents a JSON-RPC 2.0 batch: an array of individually decoded
 * request payloads that must be dispatched together and answered
 * with a single JSON array of responses (notifications excluded).
 */
final class RpcBatchRequest
{
    /**
     * @param list<array<string, mixed>> $payloads
     */
    private function __construct(
        public readonly array $payloads,
    ) {
    }

    /**
     * @param list<mixed> $decodedPayload
     */
    public static function fromDecodedArray(array $decodedPayload): self
    {
        if ($decodedPayload === [] || !array_is_list($decodedPayload)) {
            throw InvalidRequestPayloadException::emptyBatch();
        }

        return new self($decodedPayload);
    }

    public function isEmpty(): bool
    {
        return $this->payloads === [];
    }
}
