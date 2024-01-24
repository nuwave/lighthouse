<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

interface SubscriptionExceptionHandler
{
    /** Handle authentication error. */
    public function handleAuthError(\Throwable $e): void;

    /** Handle broadcast error. */
    public function handleBroadcastError(\Throwable $e): void;
}
