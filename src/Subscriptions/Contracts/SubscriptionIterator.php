<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;

interface SubscriptionIterator
{
    /**
     * Process collection of items.
     *
     * @param Collection    $items
     * @param \Closure      $callback
     * @param \Closure|null $errorHandler
     */
    public function process(Collection $items, \Closure $callback, \Closure $errorHandler = null);
}
