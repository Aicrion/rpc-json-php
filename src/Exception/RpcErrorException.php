<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Exception;

/**
 * Throw this from inside any handler method to return a custom
 * JSON-RPC error directly to the client -- code, message, and
 * optional data all pass through untouched, bypassing the generic
 * "-32000 execution failed" wrapping that ordinary exceptions get.
 *
 * Use this for expected, "business" failures the caller should be
 * able to branch on (e.g. "insufficient funds", "user not found"),
 * as opposed to genuine bugs/unexpected exceptions, which should stay
 * as plain exceptions and get wrapped/logged as internal errors.
 */
final class RpcErrorException extends JsonRpcException
{
    public function __construct(string $message, int $code = -32000, mixed $data = null)
    {
        parent::__construct($message, $code, $data);
    }
}
