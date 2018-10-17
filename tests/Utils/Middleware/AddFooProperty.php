<?php


namespace Tests\Utils\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;

class AddFooProperty
{
    const DID_RUN = 'The middleware did run successfully.';
    const DID_NOT_RUN = 'The middleware did not run.';

    public function handle(Request $request, \Closure $next)
    {
        $request->offsetSet('foo', self::DID_RUN);

        return $next($request);
    }

    public function resolve($root, $args, Context $context): string
    {
        return data_get($context->request, 'foo', self::DID_NOT_RUN);
    }
}
