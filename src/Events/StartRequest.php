<?php

namespace Nuwave\Lighthouse\Events;

use Carbon\Carbon;
use Nuwave\Lighthouse\Execution\GraphQLRequest;

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
     * GraphQL request instance.
     *
     * @var GraphQLRequest
     */
    public $request;

    /**
     * The point in time when the request started.
     *
     * @var \Carbon\Carbon
     */
    public $moment;

    /**
     * StartRequest constructor.
     *
     * @param GraphQLRequest $request
     *
     * @return void
     */
    public function __construct(GraphQLRequest $request)
    {
        $this->request = $request;
        $this->moment = Carbon::now();
    }
}
