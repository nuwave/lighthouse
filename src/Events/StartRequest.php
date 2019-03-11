<?php

namespace Nuwave\Lighthouse\Events;

use Carbon\Carbon;

/**
 * Fires right after a request reaches the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController
 */
class StartRequest
{
    /**
     * The point in time when the request started.
     *
     * @var \Carbon\Carbon
     */
    public $moment;

    /**
     * StartRequest constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->moment = Carbon::now();
    }
}
