<?php

namespace Nuwave\Lighthouse\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
     * The request sent from the client.
     *
     * @var \Illuminate\Http\Request
     */
    public $request;

    /**
     * The point in time when the request started.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->moment = Carbon::now();
    }
}
