<?php

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
    const ACCEPT = 'Accept';
    const APPLICATION_JSON = 'application/json';

    /**
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set(self::ACCEPT, self::APPLICATION_JSON);

        return $next($request);
    }
}
