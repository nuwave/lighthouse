<?php

namespace Tests\Utils\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

/**
 * @deprecated The @middleware directive will be removed in v5
 */
class Authenticate
{
    /**
     * @var string
     */
    public const MESSAGE = 'This middleware always throws.';

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function handle(Request $request, Closure $next): void
    {
        throw new AuthenticationException(self::MESSAGE);
    }
}
