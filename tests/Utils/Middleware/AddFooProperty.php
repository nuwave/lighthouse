<?php


namespace Tests\Utils\Middleware;

use Illuminate\Http\Request;

class AddFooProperty
{
    const VALUE = 'This value is set on the foo attribute.';

    public function handle(Request $request, \Closure $next)
    {
        $request->offsetSet('foo', self::VALUE);

        return $next($request);
    }
}
