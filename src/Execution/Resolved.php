<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Deferred;

class Resolved
{
    /**
     * Apply the transform function to a result or chain it onto a Deferred.
     *
     * @param  \GraphQL\Deferred|mixed $resolved The result of calling a resolver
     * @param  callable(mixed $result): mixed  $handle A function that takes that result and transforms it
     * @return \GraphQL\Deferred|mixed The transformed result or enhanced Deferred
     */
    public static function handle($resolved, callable $handle)
    {
        if ($resolved instanceof Deferred) {
            return $resolved->then($handle);
        }

        return $handle($resolved);
    }
}
