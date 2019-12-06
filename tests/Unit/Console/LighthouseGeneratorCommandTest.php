<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\LighthouseGeneratorCommand;
use Tests\TestCase;

class LighthouseGeneratorCommandTest extends TestCase
{
    public function testCommonNamespaceSingle(): void
    {
        $namespace = 'App\\Foo';

        $this->assertSame(
            $namespace,
            LighthouseGeneratorCommand::commonNamespace([$namespace])
        );
    }

    public function testCommonNamespaceMultiple(): void
    {
        $this->assertSame(
            'App',
            LighthouseGeneratorCommand::commonNamespace(['App\\Foo', 'App\\Bar', 'App\\Foo\\Bar'])
        );
        $this->assertSame(
            '',
            LighthouseGeneratorCommand::commonNamespace(['Foo', 'Bar'])
        );
    }

    public function testCommonNamespaceNone(): void
    {
        $this->assertSame(
            '',
            LighthouseGeneratorCommand::commonNamespace([])
        );
    }
}
