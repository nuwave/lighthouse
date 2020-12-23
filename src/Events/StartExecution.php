<?php

namespace Nuwave\Lighthouse\Events;

use Illuminate\Support\Carbon;

/**
 * Fires right before resolving an individual query.
 *
 * Might happen multiple times in a single request if
 * query batching is used.
 */
class StartExecution
{
    /**
     * The point in time when the query execution started.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct()
    {
        $this->moment = Carbon::now();
    }
}
