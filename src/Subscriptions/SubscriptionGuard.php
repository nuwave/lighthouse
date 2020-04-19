<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use RuntimeException;

class SubscriptionGuard implements Guard
{
    use GuardHelpers;

    public const GUARD_NAME = 'lighthouse_subscriptions';

    public function user()
    {
        return $this->user;
    }

    public function reset()
    {
        $this->user = null;
    }

    public function validate(array $credentials = [])
    {
        throw new RuntimeException('The Lighthouse subscription guard cannot be used for credential based authentication.');
    }
}
