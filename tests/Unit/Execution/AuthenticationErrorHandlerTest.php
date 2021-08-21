<?php

namespace Tests\Unit\Execution;

use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Execution\AuthenticationErrorHandler;
use Tests\TestCase;

class AuthenticationErrorHandlerTest extends TestCase
{
    public function testWrapsLaravelAuthorizationException(): void
    {
        $handler = new AuthenticationErrorHandler();

        $authenticationException = new LaravelAuthenticationException('Unauthenticated.', ['user']);
        $original = new Error('foo', null, null, [], null, $authenticationException);

        $error = null;
        $next = function (Error $e) use (&$error) {
            $error = $e;
        };

        $handler($original, $next);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertInstanceOf(AuthenticationException::class, $error->getPrevious());
        $this->assertEquals(['user'], $error->getPrevious()->guards());
    }
}
