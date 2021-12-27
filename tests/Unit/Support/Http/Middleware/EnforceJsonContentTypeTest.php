<?php

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Http\Middleware\EnforceJsonContentType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class EnforceJsonContentTypeTest extends TestCase
{
    public function testForbidGet(): void
    {
        $middleware = new EnforceJsonContentType();

        $request = new Request();
        $request->setMethod('GET');

        $this->expectException(MethodNotAllowedHttpException::class);
        $middleware->handle(
            $request,
            function () {}
        );
    }

    public function testForbidNonXHR(): void
    {
        $middleware = new EnforceJsonContentType();

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('content-type', 'multipart/form-data');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            function () {}
        );
    }

    public function testAllowXHR(): void
    {
        $middleware = new EnforceJsonContentType();

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('content-type', 'application/json');

        $result = $middleware->handle(
            $request,
            static function (): bool { return true; }
        );

        $this->assertTrue($result);
    }
}
