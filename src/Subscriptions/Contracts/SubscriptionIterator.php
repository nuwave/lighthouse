<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @param  Collection    $items
     * @param  \Closure      $cb
     * @param  \Closure|null $error
     */
    public function process(Collection $items, \Closure $cb, \Closure $error = null);
}
