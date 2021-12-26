<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Enforces the "Content-type" request header to be "application/json" and method to POST.
 *
 * It creates an easy protection against CSRF attacks as it forbids GET and non-XHR requests.
 */
class EnforceJsonContentType
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ('POST' != $request->getMethod()) {
            throw new MethodNotAllowedHttpException(['POST']);
        }

        if ('application/json' != $request->header('content-type')) {
            throw new BadRequestHttpException('Content-type should be set to application/json');
        }

        return $next($request);
    }
}
