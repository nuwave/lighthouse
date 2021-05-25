<?php

namespace Tests\Unit\Exceptions;

use Nuwave\Lighthouse\Exceptions\ValidationException;
use Tests\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testWithMessage(): void
    {
        $this->expectException(ValidationException::class);

        $exception = ValidationException::withMessage([
            'email' => 'The email or password does not match',
        ]);

        $this->assertArrayHasKey('email', $exception->extensionsContent()['validation']);

        throw $exception;
    }
}
