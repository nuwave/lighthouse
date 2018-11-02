<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Illuminate\Http\Request;

class AcceptJson
{
    public function handle(Request $request, \Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
