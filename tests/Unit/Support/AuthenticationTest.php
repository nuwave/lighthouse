<?php

namespace Tests\Unit\Support;

use InvalidArgumentException;
use Nuwave\Lighthouse\Support\Authentication;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function testGetDefaultConfigGuard()
    {
        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testIfEmptyCustomGuardGetDefault()
    {
        config()->set('lighthouse.custom_guards', []);

        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testErrorIfNotExistsCustomGuard()
    {
        $customGuard = 'some_guard';

        config()->set('lighthouse.custom_guards', [$customGuard]);

        $this->expectException(InvalidArgumentException::class);
        $expectedMessage = 'InvalidArgumentException: Auth guard ['.$customGuard.'] is not defined.';
        $this->assertEquals($expectedMessage, Authentication::getGuard());
    }
}
