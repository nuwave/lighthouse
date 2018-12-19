<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;

class ExceptionHandler implements SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param \Throwable $e
     */
    public function handleAuthError(\Throwable $e)
    {
        // Do nothing....
    }

    /**
     * Handle broadcast error.
     *
     * @param \Throwable $e
     */
    public function handleBroadcastError(\Throwable $e)
    {
        info('graphql.broadcast.exception', [
            'message' => $e->getMessage(),
            'stack' => $e->getTrace(),
        ]);
    }
}
