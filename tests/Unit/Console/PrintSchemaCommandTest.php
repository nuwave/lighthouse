<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Tests\TestCase;

class PrintSchemaCommandTest extends TestCase
{
    public function testPrintsSchemaAsGraphQLSDL(): void
    {
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute([]);

        $this->assertContains($this->schema, $tester->getDisplay());
    }
}
