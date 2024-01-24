<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Always set the `Accept: application/json` header.
 *
 * This makes it easier to do simple GET requests from clients
 * with limited HTTP configuration options and always receive
 * a proper result, even in case of unforeseen errors.
 */
class AcceptJson
{
    public const ACCEPT = 'Accept';

    public const APPLICATION_JSON = 'application/json';

    public function handle(Request $request, \Closure $next): mixed
    {
        $request->headers->set(self::ACCEPT, self::APPLICATION_JSON);

        return $next($request);
    }
}
