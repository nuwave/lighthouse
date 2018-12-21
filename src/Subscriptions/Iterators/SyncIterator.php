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
     * @param \Closure      $cb
     * @param \Closure|null $error
     */
    public function process(Collection $items, \Closure $cb, \Closure $error = null)
    {
        $items->each(function ($item) use ($cb, $error) {
            try {
                $cb($item);
            } catch (\Exception $e) {
                if (! $error) {
                    throw $e;
                }

                $error($e);
            }
        });
    }
}
