<?php

namespace Nuwave\Lighthouse\Subscriptions\Iterators;

use Closure;
use Exception;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;

class SyncIterator implements SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  \Closure  $cb
     * @param  \Closure|null  $error
     * @return void
     */
    public function process(Collection $items, Closure $cb, Closure $error = null): void
    {
        $items->each(function ($item) use ($cb, $error): void {
            try {
                $cb($item);
            } catch (Exception $e) {
                if (! $error) {
                    throw $e;
                }

                $error($e);
            }
        });
    }
}
