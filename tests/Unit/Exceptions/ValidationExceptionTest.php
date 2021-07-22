<?php

namespace Tests\Unit\Exceptions;

use Illuminate\Validation\ValidationException as LaravelValidationException;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Tests\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testWithMessages(): void
    {
        $rule = 'email';
        $message = 'The email or password does not match';
        $exception = ValidationException::withMessages([$rule => $message]);

        $validation = $exception->extensionsContent()[ValidationException::CATEGORY];
        $this->assertSame([$message], $validation[$rule]);
    }

    public function testFromLaravel(): void
    {
        $rule = 'email';
        $message = 'The email or password does not match';
        $laravelException = LaravelValidationException::withMessages([$rule => $message]);
        $exception = ValidationException::fromLaravel($laravelException);

        $validation = $exception->extensionsContent()[ValidationException::CATEGORY];
        $this->assertSame([$message], $validation[$rule]);
    }
}
