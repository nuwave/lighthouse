<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Ensures the request is not vulnerable to cross-site request forgery.
 *
 * - The method must not be GET
 * - If the method is POST, the content type must not equal one set by form
 */
class EnsureXHR
{
    /**
     * @see https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fs-enctype
     */
    public const FORM_CONTENT_TYPES = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain',
    ];

    /**
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $method = $request->getRealMethod();

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

        if (null === $contentType || '' === $contentType) {
            throw new BadRequestHttpException('Content-Type header must be set');
        }

        if (Str::startsWith($contentType, static::FORM_CONTENT_TYPES)) {
            throw new BadRequestHttpException("Content-Type $contentType is forbidden");
        }

        return $next($request);
    }
}
