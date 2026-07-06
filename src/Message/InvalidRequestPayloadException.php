<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Message;

use Aicrion\JsonRpc\Exception\JsonRpcException;

/**
 * Thrown while parsing a raw payload into an RpcRequest.
 */
final class InvalidRequestPayloadException extends JsonRpcException
{
    public static function missingMethod(): self
    {
        return new self('The "method" field is required and must be a non-empty string.', -32600);
    }

    public static function invalidParams(): self
    {
        return new self('The "params" field must be an object or an array.', -32602);
    }

    public static function invalidId(): self
    {
        return new self('The "id" field must be a string, an integer, or absent.', -32600);
    }

    public static function emptyBatch(): self
    {
        return new self('A batch request must be a non-empty JSON array.', -32600);
    }

    public static function malformedJson(): self
    {
        return new self('The request body is not valid JSON.', -32700);
    }
}
