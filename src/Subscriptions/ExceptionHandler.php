<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;

class ExceptionHandler implements SubscriptionExceptionHandler
{
    /** Handle authentication error. */
    public function handleAuthError(\Throwable $e): void
    {
        // Do nothing....
    }

    /** Handle broadcast error. */
    public function handleBroadcastError(\Throwable $e): void
    {
        info('graphql.broadcast.exception', [
            'message' => $e->getMessage(),
            'stack' => $e->getTrace(),
        ]);
    }
}
