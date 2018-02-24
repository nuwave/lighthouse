<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use Nuwave\Lighthouse\Tests\TestCase;

use Nuwave\Lighthouse\Schema\Directives\ScalarDirective;

class DirectiveContainerTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(ScalarDirective::class, directives()->handler('scalar'));
    }
}
