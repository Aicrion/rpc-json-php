<?php

declare(strict_types=1);

namespace Aicrion\JsonRpc\Pipeline;

use Aicrion\JsonRpc\Contract\ParameterBinder;
use ReflectionMethod;

/**
 * Binds JSON-RPC params onto a method signature. Supports both
 * positional arrays ([1, 2]) and named/object params ({"a": 1, "b": 2}),
 * falling back to declared default values when a named param is absent.
 */
final class DefaultParameterBinder implements ParameterBinder
{
    public function bind(ReflectionMethod $method, array $params): array
    {
        $isPositional = array_is_list($params);
        $arguments = [];

        foreach ($method->getParameters() as $index => $parameter) {
            $name = $parameter->getName();

            if ($isPositional) {
                if (array_key_exists($index, $params)) {
                    $arguments[] = $params[$index];
                    continue;
                }
            } elseif (array_key_exists($name, $params)) {
                $arguments[] = $params[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }
        }

        return $arguments;
    }
}
