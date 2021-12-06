<?php

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionGuard;

/**
 * Logs in the subscriber as their subscription is resolved.
 */
class AuthenticatingSyncIterator implements SubscriptionIterator
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    /**
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $authFactory;

    public function __construct(ConfigRepository $configRepository, AuthFactory $authFactory)
    {
        $this->configRepository = $configRepository;
        $this->authFactory = $authFactory;
    }

    public function process(Collection $subscribers, Closure $handleSubscriber, Closure $handleError = null): void
    {
        // Store the previous default guard name so we can restore it after we're done
        $previousGuardName = $this->configRepository->get('auth.defaults.guard');

        // Set our subscription guard as the default guard for the application
        $this->authFactory->shouldUse(SubscriptionGuard::GUARD_NAME);

        /** @var \Nuwave\Lighthouse\Subscriptions\SubscriptionGuard $guard */
        $guard = $this->authFactory->guard(SubscriptionGuard::GUARD_NAME);

        $subscribers->each(static function (Subscriber $item) use ($handleSubscriber, $handleError, $guard): void {
            // If there is an authenticated user set in the context, set that user as the authenticated user
            $user = $item->context->user();
            if ($user !== null) {
                $guard->setUser($user);
            }

            try {
                $handleSubscriber($item);
            } catch (Exception $e) {
                if ($handleError === null) {
                    throw $e;
                }

                $handleError($e);
            } finally {
                // Unset the authenticated user after each iteration to restore the guard to a unauthenticated state
                $guard->reset();
            }
        });

        // Restore the previous default guard name
        $this->authFactory->shouldUse($previousGuardName);
    }
}
