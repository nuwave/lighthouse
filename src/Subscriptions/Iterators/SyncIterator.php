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
     */
    public function process(Collection $items, Closure $cb, Closure $error = null): void
    {
        $items->each(static function ($item) use ($cb, $error): void {
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
