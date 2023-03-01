<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use GraphQL\Error\Error;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Execution\ValidationErrorHandler;
use Tests\TestCase;

final class ValidationErrorHandlerTest extends TestCase
{
    public function testWrapsLaravelValidation(): void
    {
        $handler = new ValidationErrorHandler();

        $validationException = LaravelValidationException::withMessages([]);
        $original = new Error('foo', null, null, [], null, $validationException);

        $error = null;
        $next = static function (Error $e) use (&$error) {
            $error = $e;
        };

        $handler($original, $next);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertInstanceOf(ValidationException::class, $error->getPrevious());
    }
}
