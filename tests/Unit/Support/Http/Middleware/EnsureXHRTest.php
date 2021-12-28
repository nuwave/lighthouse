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
            static function (): void {}
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
            static function (): void {}
        );
    }

    /**
     * @return iterable<int, array{string}>
     */
    public function formContentTypes(): iterable
    {
        foreach (EnsureXHR::FORM_CONTENT_TYPES as $contentType) {
            yield [$contentType];
        }
    }

    public function testForbidEmptyContentType(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            static function (): void {}
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
