<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;
use Throwable;

class ExceptionHandler implements SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     */
    public function handleAuthError(Throwable $e): void
    {
        // Do nothing....
    }

    /**
     * Handle broadcast error.
     */
    public function handleBroadcastError(Throwable $e): void
    {
        info('graphql.broadcast.exception', [
            'message' => $e->getMessage(),
            'stack' => $e->getTrace(),
        ]);
    }
}
