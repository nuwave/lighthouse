<?php

namespace Tests\Utils\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class Authenticate
{
    const MESSAGE = 'This middleware always throws.';

    public function handle(Request $request, \Closure $next)
    {
        throw new AuthenticationException(self::MESSAGE);
    }
}
