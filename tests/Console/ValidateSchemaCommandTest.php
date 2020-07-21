<?php

namespace Tests\Console;

use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Tests\TestCase;

class ValidateSchemaCommandTest extends TestCase
{
    public function testValidatesCorrectSchema(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                arg: ID @eq
            ): ID @guard
        }
        ';
        $tester = $this->commandTester(new ValidateSchemaCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testFailsValidationForIncorrectSchema(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @unknown
        }
        ';
        $tester = $this->commandTester(new PrintSchemaCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
