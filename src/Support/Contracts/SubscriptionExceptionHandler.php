<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface SubscriptionExceptionHandler
{
    /**
     * Handle authentication error.
     *
     * @param \Exception $e
     */
    public function handleAuthError(\Exception $e);

    /**
     * Handle broadcast error.
     *
     * @param \Exception $e
     */
    public function handleBroadcastError(\Exception $e);
}
