<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class Utils
{
    /**
     * Attempt to find a given class in the given namespaces.
     *
     * If the class itself exists, it is simply returned as is.
     * Else, the given namespaces are tried in order.
     *
     * @param  string  $classCandidate
     * @param  array  $namespacesToTry
     * @param  callable  $determineMatch
     * @return string|null
     */
    public static function namespaceClassname(string $classCandidate, array $namespacesToTry, callable $determineMatch): ?string
    {
        if ($determineMatch($classCandidate)) {
            return $classCandidate;
        }

        // Stop if the class is found or we are out of namespaces to try
        while (! empty($namespacesToTry)) {
            // Pop off the first namespace and try it
            $className = array_shift($namespacesToTry).'\\'.$classCandidate;

            if ($determineMatch($className)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Construct a closure that passes through the arguments.
     *
     * @param  string  $className This class is resolved through the container.
     * @param  string  $methodName The method that gets passed the arguments of the closure.
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function constructResolver(string $className, string $methodName): Closure
    {
        if (! method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'");
        }

        return Closure::fromCallable([app($className), $methodName]);
    }
}
