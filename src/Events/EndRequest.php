<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires right after building the HTTP response in the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Http\GraphQLController
 */
class EndRequest
{
    /** The point in time when the response was ready. */
    public Carbon $moment;

    public function __construct(
        /**
         * The response that is about to be sent to the client.
         */
        public Response $response,
    ) {
        $this->moment = Carbon::now();
    }
}
