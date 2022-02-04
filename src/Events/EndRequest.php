<?php

namespace Nuwave\Lighthouse\Events;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires right after building the HTTP response in the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController
 */
class EndRequest
{
    /**
     * The response that is about to be sent to the client.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public $response;

    /**
     * The point in time when the response was ready.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->moment = Carbon::now();
    }
}
