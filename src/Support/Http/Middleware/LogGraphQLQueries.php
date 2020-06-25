<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Psr\Log\LoggerInterface;

/**
 * Logs every incoming GraphQL query.
 */
class LogGraphQLQueries
{
    const MESSAGE = 'Received GraphQL query';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {
        $this->logger->info(
            self::MESSAGE,
            $request->json()->all()
        );

        return $next($request);
    }
}
