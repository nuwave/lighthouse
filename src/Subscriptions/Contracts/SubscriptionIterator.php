<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process subscribers through the given callbacks.
     *
     * @param  \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Subscriptions\Subscriber>  $subscribers  the subscribers that receive the current subscription
     * @param  \Closure(\Nuwave\Lighthouse\Subscriptions\Subscriber): void  $handleSubscriber Receives each subscriber in the given collection
     * @param  \Closure|null  $handleError  is called when $handleSubscriber throws
     */
    public function process(Collection $subscribers, \Closure $handleSubscriber, ?\Closure $handleError = null): void;
}
