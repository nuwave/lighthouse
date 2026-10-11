<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Execution\AuthenticationErrorHandler;
use Tests\TestCase;

final class AuthenticationErrorHandlerTest extends TestCase
{
    public function testWrapsLaravelAuthenticationException(): void
    {
        $handler = new AuthenticationErrorHandler();

        $authenticationException = new LaravelAuthenticationException('Unauthenticated.', ['user']);
        $original = new Error(message: 'foo', previous: $authenticationException);

        $error = null;
        $next = static function (Error $e) use (&$error): void {
            $error = $e;
        };

        $handler($original, $next);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertInstanceOf(AuthenticationException::class, $error->getPrevious());
        $this->assertSame(['user'], $error->getPrevious()->guards());
    }
}
