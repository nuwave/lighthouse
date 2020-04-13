<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Closure;
use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @return void
     */
    public function process(Collection $items, Closure $cb, Closure $error = null);
}
