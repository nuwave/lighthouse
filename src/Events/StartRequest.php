<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fires right after a request reaches the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Http\GraphQLController
 */
class StartRequest
{
    /** The point in time when the request started. */
    public Carbon $moment;

    public function __construct(
        /**
         * The request sent from the client.
         */
        public Request $request,
    ) {
        $this->moment = Carbon::now();
    }
}
