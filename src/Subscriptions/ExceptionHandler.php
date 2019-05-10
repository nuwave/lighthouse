<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Throwable;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;

class ExceptionHandler implements SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handleAuthError(Throwable $e): void
    {
        // Do nothing....
    }

    /**
     * Handle broadcast error.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handleBroadcastError(Throwable $e): void
    {
        info('graphql.broadcast.exception', [
            'message' => $e->getMessage(),
            'stack' => $e->getTrace(),
        ]);
    }
}
