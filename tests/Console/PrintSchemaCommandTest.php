<?php

namespace Tests\Console;

use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Tests\TestCase;

class PrintSchemaCommandTest extends TestCase
{
    public function testPrintsSchemaAsGraphQLSDL(): void
    {
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute([]);

        $this->assertStringContainsString($this->schema, $tester->getDisplay());
    }

    public function testPrintsSchemaAsJSON(): void
    {
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute(['--json' => true]);

        $json = $tester->getDisplay();

        $this->assertJson($json);
        $this->assertStringContainsString('"name":"foo"', $json, 'Should contain the introspection result for the schema.');
    }

    public function testWritesSchema(): void
    {
        Storage::fake();
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute(['--write' => true]);

        $this->assertStringContainsString(
            $this->schema,
            Storage::get(PrintSchemaCommand::GRAPHQL_FILENAME)
        );
    }
}
