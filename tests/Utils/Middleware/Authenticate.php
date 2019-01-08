<?php

namespace Tests\Utils\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class Authenticate
{
    /**
     * @var string
     */
    const MESSAGE = 'This middleware always throws.';

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function handle(Request $request, \Closure $next): void
    {
        throw new AuthenticationException(self::MESSAGE);
    }
}
