<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

class Resolved
{
    /**
     * Apply the transform function to a result or chain it onto a SyncPromise.
     *
     * @param  \GraphQL\Executor\Promise\Adapter\SyncPromise|mixed  $resolved  The result of calling a resolver
     * @param  callable(mixed $result): mixed  $handle A function that takes that result and transforms it
     *
     * @return \GraphQL\Executor\Promise\Adapter\SyncPromise|mixed The transformed result or enhanced SyncPromise
     */
    public static function handle($resolved, callable $handle)
    {
        if ($resolved instanceof SyncPromise) {
            return $resolved->then($handle);
        }

        return $handle($resolved);
    }
}
