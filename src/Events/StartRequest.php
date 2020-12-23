<?php

namespace Nuwave\Lighthouse\Events;

use lluminate\Support\Carbon;
use Illuminate\Http\Request;

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
     * HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    public $request;

    /**
     * The point in time when the request started.
     *
     * @var \lluminate\Support\Carbon
     */
    public $moment;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->moment = Carbon::now();
    }
}
