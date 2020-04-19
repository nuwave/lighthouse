<?php

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionGuard;

class GuardContextSyncIterator implements SubscriptionIterator
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $configRepository;

    /**
     * @var \Illuminate\Contracts\Auth\Factory
     */
    private $authFactory;

    public function __construct(Repository $configRepository, Factory $authFactory)
    {
        $this->authFactory = $authFactory;
        $this->configRepository = $configRepository;
    }

    /**
     * Process collection of items.
     *
     * @param  \Illuminate\Support\Collection<Subscriber>  $items
     */
    public function process(Collection $items, Closure $cb, Closure $error = null): void
    {
        // Store the previous default guard name so we can restore it after we're done
        $previousGuardName = $this->configRepository->get('auth.defaults.guard');

        // Set our subscription guard as the default guard for the application
        $this->authFactory->shouldUse(SubscriptionGuard::GUARD_NAME);

        /** @var \Nuwave\Lighthouse\Subscriptions\SubscriptionGuard $guard */
        $guard = $this->authFactory->guard(SubscriptionGuard::GUARD_NAME);

        $items->each(static function (Subscriber $item) use ($cb, $error, $guard): void {
            // If there is an authenticated user set in the context, set that user as the authenticated user
            if ($item->context->user()) {
                $guard->setUser($item->context->user());
            }

            try {
                $cb($item);
            } catch (Exception $e) {
                if (! $error) {
                    throw $e;
                }

                $error($e);
            } finally {
                // Unset the authenticated user after each iteration to restore the guard to a unauthenticated state
                $guard->reset();
            }
        });

        // Restore the previous default guard name
        $this->authFactory->shouldUse($previousGuardName);
    }
}
