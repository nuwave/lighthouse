<?php

namespace Tests\Utils\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * @deprecated will be removed with @middleware
 */
class CountRuns
{
    /**
     * Track how often this middleware was run.
     *
     * @var int
     */
    public static $runCounter = 0;

    public function handle(Request $request, Closure $next)
    {
        self::$runCounter++;

        return $next($request);
    }

    public function resolve(): int
    {
        return self::$runCounter;
    }
}
