<?php

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Closure;
use Exception;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;

class SyncIterator implements SubscriptionIterator
{
    public function process(Collection $subscribers, Closure $handleSubscriber, Closure $onError = null): void
    {
        $subscribers->each(static function ($item) use ($handleSubscriber, $onError): void {
            try {
                $handleSubscriber($item);
            } catch (Exception $e) {
                if (! $onError) {
                    throw $e;
                }

                $onError($e);
            }
        });
    }
}
