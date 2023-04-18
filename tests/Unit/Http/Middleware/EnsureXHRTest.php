<?php declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Http\Middleware\EnsureXHR;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

final class EnsureXHRTest extends TestCase
{
    public function testForbidGet(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('GET');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            static function (): void {},
        );
    }

    public function testHandleMethodOverride(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $request->headers->set('content-type', 'multipart/form-data');
        $request->request->set('_method', 'PUT');
        $request->enableHttpMethodParameterOverride();

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            static function (): void {},
        );
    }

    public function testAllowNonStandardMethod(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('PUT');

        $response = new Response();

        $result = $middleware->handle(
            $request,
            static fn (): Response => $response,
        );

        $this->assertSame($response, $result);
    }

    /** @dataProvider formContentTypes */
    public function testForbidFormContentType(string $contentType): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $request->headers->set('content-type', $contentType);

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            static function (): void {},
        );
    }

    /** @return array{array{string}} */
    public static function formContentTypes(): array
    {
        return [
            ['application/x-www-form-urlencoded'],
            ['multipart/form-data'],
            ['text/plain'],
            ['multipart/form-data; boundary=-------12345'],
            ['text/plain; encoding=utf-8'],
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
            static function (): void {},
        );
    }

    public function testAllowXRequestedWithHeader(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = new Response();

        $result = $middleware->handle(
            $request,
            static fn (): Response => $response,
        );

        $this->assertSame($response, $result);
    }

    public function testForbidInvalidXRequestedWithHeader(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $request->headers->set('X-Requested-With', 'InvalidValue');

        $this->expectException(BadRequestHttpException::class);
        $middleware->handle(
            $request,
            static function (): void {},
        );
    }

    public function testAllowXHR(): void
    {
        $middleware = new EnsureXHR();

        $request = new Request();
        $request->setMethod('POST');

        $request->headers->set('content-type', 'application/json');

        $response = new Response();

        $result = $middleware->handle(
            $request,
            static fn (): Response => $response,
        );

        $this->assertSame($response, $result);
    }
}
