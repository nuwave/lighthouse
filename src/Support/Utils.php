<?php

namespace Nuwave\Lighthouse\Support;

class Utils
{
    /**
     * Attempt to find a given class in the given namespaces.
     *
     * If the class itself exists, it is simply returned as is.
     * Else, the given namespaces are tried in order.
     *
     * @param  string   $classCandidate
     * @param  array    $namespacesToTry
     * @param  callable $determineMatch
     *
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
}
