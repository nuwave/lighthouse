<?php

namespace Tests\Unit\Exceptions;

use Nuwave\Lighthouse\Exceptions\ValidationException;
use Tests\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testWithMessage()
    {
        $this->expectException(ValidationException::class);

        throw ValidationException::withMessage([
            'email' => 'The email or password does not match',
        ]);
    }
}
