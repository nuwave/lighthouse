<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;

class SyncIterator implements SubscriptionIterator
{
    public function process(Collection $subscribers, \Closure $handleSubscriber, ?\Closure $handleError = null): void
    {
        $subscribers->each(static function ($item) use ($handleSubscriber, $handleError): void {
            try {
                $handleSubscriber($item);
            } catch (\Exception $exception) {
                if ($handleError === null) {
                    throw $exception;
                }

                $handleError($exception);
            }
        });
    }
}
