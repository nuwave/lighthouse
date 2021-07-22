<?php

namespace Tests\Unit\Execution;

use GraphQL\Error\Error;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Execution\ValidationErrorHandler;
use Tests\TestCase;

class ValidationErrorHandlerTest extends TestCase
{
    public function testWrapsLaravelValidation(): void
    {
        $handler = new ValidationErrorHandler();

        $validationException = LaravelValidationException::withMessages([]);
        $error = new Error('foo', null, null, [], null, $validationException);

        $exception = null;
        $next = function (ValidationException $e) use (&$exception) {
            $exception = $e;
        };

        $handler($error, $next);
        $this->assertInstanceOf(ValidationException::class, $exception);
    }
}
