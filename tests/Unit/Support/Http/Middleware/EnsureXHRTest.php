<?php

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Http\Middleware\EnsureXHR;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class EnsureXHRTest extends TestCase
{
    public function testForbidGet(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('GET');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            function () {}
        );
    }

    public function testAllowNonStandardMethod(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('PUT');

        $result = $middleware->handle(
            $request,
            static function (): bool { return true; }
        );

        $this->assertTrue($result);
    }

    /**
     * @dataProvider formContentTypes
     */
    public function testForbidFormContentType(string $contentType): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('content-type', $contentType);

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            function () {}
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function formContentTypes(): array
    {
        return [
            ['application/x-www-form-urlencoded'],
            ['multipart/form-data'],
            ['text/plain'],
        ];
    }

    public function testForbidEmptyContentType(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            function () {}
        );
    }


    public function testAllowXHR(): void
    {
        $middleware = new EnsureXHR();

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
