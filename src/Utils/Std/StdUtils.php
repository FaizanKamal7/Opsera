<?php

declare(strict_types=1);

namespace App\Utils\Std;

final class StdUtils
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public static function getCallableParameterCount(callable $callable): int
    {
        if (\is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } elseif (\is_string($callable) && str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable);
            $reflection = new \ReflectionMethod($class, $method);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        return $reflection->getNumberOfParameters();
    }
}
