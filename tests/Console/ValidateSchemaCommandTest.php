<?php

namespace Tests\Console;

use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
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

    public function testFailsValidationUnknownDirective(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @unknown
        }
        ';
        $tester = $this->commandTester(new ValidateSchemaCommand());

        $this->expectException(DirectiveException::class);
        $tester->execute([]);
    }

    public function testFailsValidationDirectiveInWrongLocation(): void
    {
        $this->markTestSkipped('This validation needs to be in the upstream webonyx/graphql-php validation');

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
        $this->schema = /** @lang GraphQL */ '
        type Query @field {
            foo: ID @eq
        }
        ';
        $tester = $this->commandTester(new ValidateSchemaCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
