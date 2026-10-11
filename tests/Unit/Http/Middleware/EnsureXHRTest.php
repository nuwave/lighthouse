<?php declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Http\Middleware\EnsureXHR;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('formContentTypes')]
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

    /** @return iterable<array{string}> */
    public static function formContentTypes(): iterable
    {
        yield ['application/x-www-form-urlencoded'];
        yield ['multipart/form-data'];
        yield ['text/plain'];
        yield ['multipart/form-data; boundary=-------12345'];
        yield ['text/plain; encoding=utf-8'];
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
