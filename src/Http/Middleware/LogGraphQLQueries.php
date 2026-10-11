<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Http\Middleware;

use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Logs every incoming GraphQL query.
 */
class LogGraphQLQueries
{
    public const MESSAGE = 'Received GraphQL query.';

    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function handle(Request $request, \Closure $next): mixed
    {
        $jsonParameters = $request->json()->all();
        $this->logger->info(self::MESSAGE, $jsonParameters);

        return $next($request);
    }
}
