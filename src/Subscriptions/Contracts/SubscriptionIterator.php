<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Closure;
use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process subscribers through the given callbacks.
     *
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>  $subscribers  the subscribers that receive the current subscription
     * @param  \Closure(\Nuwave\Lighthouse\Subscriptions\Subscriber): void  $handleSubscriber Receives each subscriber in the passed in collection
     * @param  \Closure|null  $handleError  is called when $handleSubscriber throws
     *
     * @return void
     */
    public function process(Collection $subscribers, Closure $handleSubscriber, Closure $handleError = null);
}
