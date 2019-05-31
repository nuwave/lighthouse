<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Throwable;

interface SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handleAuthError(Throwable $e);

    /**
     * Handle broadcast error.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handleBroadcastError(Throwable $e);
}
