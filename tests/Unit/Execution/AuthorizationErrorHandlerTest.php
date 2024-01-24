<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Execution\AuthorizationErrorHandler;
use Tests\TestCase;

final class AuthorizationErrorHandlerTest extends TestCase
{
    public function testWrapsLaravelAuthorizationException(): void
    {
        $handler = new AuthorizationErrorHandler();

        $authorizationException = new LaravelAuthorizationException();
        $original = new Error('foo', null, null, [], null, $authorizationException);

        $error = null;
        $next = static function (Error $e) use (&$error): void {
            $error = $e;
        };

        $handler($original, $next);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertInstanceOf(AuthorizationException::class, $error->getPrevious());
    }
}
