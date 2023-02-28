<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Always set the Accept: application/json header.
 *
 * This makes it easier to do simple GET requests from clients
 * with limited HTTP configuration options and always receive
 * a proper result, even in case of unforeseen errors.
 */
class AcceptJson
{
    public const ACCEPT = 'Accept';
    public const APPLICATION_JSON = 'application/json';

    public function handle(Request $request, \Closure $next): Response|JsonResponse
    {
        $request->headers->set(self::ACCEPT, self::APPLICATION_JSON);

        return $next($request);
    }
}
