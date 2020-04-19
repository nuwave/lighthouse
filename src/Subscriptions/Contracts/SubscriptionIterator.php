<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Closure;
use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process subscribers through the given callbacks.
     *
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>  $subscribers
     * The subscribers that receive the current subscription.
     *
     * @param  \Closure  $handleSubscriber
     * Receives each subscriber in the passed in collection.
     * function(\Nuwave\Lighthouse\Subscriptions\Subscriber $subscriber)
     *
     * @param  \Closure|null  $handleError
     * Is called when $handleSubscriber throws.
     * @return void
     */
    public function process(Collection $subscribers, Closure $handleSubscriber, Closure $handleError = null);
}
