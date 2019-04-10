<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Throwable;

interface SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param  \Throwable  $e
     */
    public function handleAuthError(Throwable $e);

    /**
     * Handle broadcast error.
     *
     * @param  \Throwable  $e
     */
    public function handleBroadcastError(Throwable $e);
}
