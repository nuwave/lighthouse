<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The middleware ensures that request comes from Javascript XHR API:
 * The method must not be GET
 * If the method is POST, the content type must not equal to one set by form
 *  https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fs-enctype.
 *
 * It creates an easy protection against CSRF.
 */
class EnsureXHR
{
    private const FORBIDDEN_CONTENT_TYPES = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain',
    ];

    /**
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $method = $request->getMethod();

        if ('GET' === $method) {
            throw new BadRequestHttpException('GET requests are forbidden');
        }

        if ('POST' !== $method) {
            return $next($request);
        }

        $contentType = $request->header('content-type', '');
        if (is_array($contentType)) {
            $contentType = $contentType[0];
        }

        if (empty($contentType)) {
            throw new BadRequestHttpException('Content-Type header must be set');
        }

        if (in_array($contentType, self::FORBIDDEN_CONTENT_TYPES)) {
            throw new BadRequestHttpException("Content-Type $contentType is forbidden");
        }

        return $next($request);
    }
}
