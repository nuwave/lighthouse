<?php

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;

class SyncIterator implements SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @param Collection    $items
     * @param \Closure      $callback
     * @param \Closure|null $errorHandler
     */
    public function process(Collection $items, \Closure $callback, \Closure $errorHandler = null)
    {
        $items->each(function ($item) use ($callback, $errorHandler) {
            try {
                $callback($item);
            } catch (\Exception $e) {
                if (! $errorHandler) {
                    throw $e;
                }

                $errorHandler($e);
            }
        });
    }
}
