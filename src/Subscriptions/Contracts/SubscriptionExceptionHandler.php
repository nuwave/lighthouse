<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

interface SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @return void
     */
    public function handleAuthError(\Throwable $e);

    /**
     * Handle broadcast error.
     *
     * @return void
     */
    public function handleBroadcastError(\Throwable $e);
}
