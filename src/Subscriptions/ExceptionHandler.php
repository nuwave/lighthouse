<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;

class ExceptionHandler implements SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param \Exception $e
     */
    public function handleAuthError(\Exception $e)
    {
        // Do nothing....
    }

    /**
     * Handle broadcast error.
     *
     * @param \Exception $e
     */
    public function handleBroadcastError(\Exception $e)
    {
        info('graphql.broadcast.exception', [
            'message' => $e->getMessage(),
            'stack' => $e->getTrace(),
        ]);
    }
}
