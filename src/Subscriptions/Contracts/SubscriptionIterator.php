<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Closure;
use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  \Closure  $cb
     * @param  \Closure|null  $error
     * @return void
     */
    public function process(Collection $items, Closure $cb, Closure $error = null);
}
