<?php

namespace Tests\Unit\Support;

use InvalidArgumentException;
use Nuwave\Lighthouse\Support\Authentication;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class AuthenticationTest extends DBTestCase
{
    public function testGetDefaultConfigLighthouseGuard(): void
    {
        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testIfEmptyAuthGuardsGetDefault(): void
    {
        config()->set('auth.guards', []);

        $this->assertEquals(config('lighthouse.guard'), Authentication::getGuard());
    }

    public function testGetAuthUserGuard(): void
    {
        $user = factory(User::class)->make();
        $this->be($user);

        $expectedGuard = 'web';
        $this->assertEquals($expectedGuard, Authentication::getGuard());
    }

    public function testErrorIfNotExistsCustomGuard(): void
    {
        $customGuard = 'some_guard';

        config()->set('auth.guards', [$customGuard]);

        $this->expectException(InvalidArgumentException::class);
        $expectedMessage = 'InvalidArgumentException: Auth guard ['.$customGuard.'] is not defined.';
        $this->assertEquals($expectedMessage, Authentication::getGuard());
    }
}
