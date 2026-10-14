<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

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
    public function __construct(
        protected ConfigRepository $configRepository,
        protected AuthFactory $authFactory,
    ) {}

    public function process(Collection $subscribers, \Closure $handleSubscriber, ?\Closure $handleError = null): void
    {
        // Store the previous default guard name, so we can restore it after we're done
        $previousGuardName = $this->configRepository->get('auth.defaults.guard');

        // Store the previous default Lighthouse guard name, so we can restore it after we're done
        $defaultLighthouseGuardNames = $this->configRepository->get('lighthouse.guards');

        // Set our subscription guard as the default guard for Lighthouse
        $this->configRepository->set('lighthouse.guards', [SubscriptionGuard::GUARD_NAME]);

        // Set our subscription guard as the default guard for the application
        $this->authFactory->shouldUse(SubscriptionGuard::GUARD_NAME);

        $guard = $this->authFactory->guard(SubscriptionGuard::GUARD_NAME);
        assert($guard instanceof SubscriptionGuard);

        try {
            $subscribers->each(static function (Subscriber $item) use ($handleSubscriber, $handleError, $guard): void {
                // If there is an authenticated user set in the context, set that user as the authenticated user
                $user = $item->context->user();
                if ($user !== null) {
                    $guard->setUser($user);
                }

                try {
                    $handleSubscriber($item);
                } catch (\Exception $exception) {
                    if ($handleError === null) {
                        throw $exception;
                    }

                    $handleError($exception);
                } finally {
                    // Unset the authenticated user after each iteration to restore the guard to its unauthenticated state
                    $guard->reset();
                }
            });
        } finally {
            // Restore the previous default Lighthouse guard name
            $this->configRepository->set('lighthouse.guards', $defaultLighthouseGuardNames);

            // Restore the previous default guard name
            $this->authFactory->shouldUse($previousGuardName);
        }
    }
}
