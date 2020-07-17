<?php

namespace Tests\Console;

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

    public function testCommonNamespaceSharedBase(): void
    {
        $this->assertSame(
            'App',
            LighthouseGeneratorCommand::commonNamespace(['App\\Foo', 'App\\Bar', 'App\\Foo\\Bar'])
        );
        $this->assertSame(
            'Foo',
            LighthouseGeneratorCommand::commonNamespace(['Foo', 'Bar'])
        );
    }

    public function testCommonNamespaceNothingShared(): void
    {
        $first = 'App\\Foo';

        $this->assertSame(
            $first,
            LighthouseGeneratorCommand::commonNamespace([$first, 'Foo\\Bar'])
        );
    }

    public function testCommonNamespaceNone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LighthouseGeneratorCommand::commonNamespace([]);
    }
}
