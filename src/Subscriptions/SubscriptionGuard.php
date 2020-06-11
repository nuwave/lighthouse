<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use RuntimeException;

class SubscriptionGuard implements Guard
{
    use GuardHelpers;

    public const GUARD_NAME = 'lighthouse_subscriptions';

    /**
     * The currently authenticated user.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected $user;

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function reset(): void
    {
        $this->user = null;
    }

    /**
     * @param  array<mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        throw new RuntimeException('The Lighthouse subscription guard cannot be used for credential based authentication.');
    }
}
