<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

class SubscriptionGuard implements Guard
{
    use GuardHelpers;

    public const GUARD_NAME = 'lighthouse_subscriptions';

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function reset(): void
    {
        // @phpstan-ignore-next-line GuardHelpers in old Laravel versions has non-nullable PHPDoc for this type
        $this->user = null;
    }

    /** @param  array<mixed>  $credentials */
    public function validate(array $credentials = []): bool
    {
        throw new \RuntimeException('The Lighthouse subscription guard cannot be used for credential based authentication.');
    }
}
