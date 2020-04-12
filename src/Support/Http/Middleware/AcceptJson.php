<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Always set the Accept: application/json header.
 *
 * This makes it easier to do simple GET requests from clients
 * with limited HTTP configuration options and always receive
 * a proper result, even in case of unforeseen errors.
 */
class AcceptJson
{
    /**
     * Force the Accept header of the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
