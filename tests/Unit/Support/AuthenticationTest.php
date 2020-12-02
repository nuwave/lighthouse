<?php

namespace Tests\Unit\Support;

use InvalidArgumentException;
use Nuwave\Lighthouse\Support\Authentication;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class AuthenticationTest extends DBTestCase
{
    public function testGetDefaultConfigGuard(): void
    {
        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testIfEmptyCustomGuardGetDefault(): void
    {
        config()->set('lighthouse.custom_guards', []);

        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testGetCustomConfigGuard(): void
    {
        $customGuard = 'web';

        config()->set('lighthouse.custom_guards', [$customGuard]);

        $user = factory(User::class)->make();
        $this->be($user);

        $this->assertEquals($customGuard, Authentication::getGuard());
    }

    public function testErrorIfNotExistsCustomGuard(): void
    {
        $customGuard = 'some_guard';

        config()->set('lighthouse.custom_guards', [$customGuard]);

        $this->expectException(InvalidArgumentException::class);
        $expectedMessage = 'InvalidArgumentException: Auth guard [' . $customGuard . '] is not defined.';
        $this->assertEquals($expectedMessage, Authentication::getGuard());
    }
}
