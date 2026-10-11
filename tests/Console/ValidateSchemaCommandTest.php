<?php declare(strict_types=1);

namespace Tests\Console;

use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\TestCase;

final class ValidateSchemaCommandTest extends TestCase
{
    public function testValidatesCorrectSchema(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                arg: ID @eq
            ): ID @guard
        }
        GRAPHQL;
        $tester = $this->commandTester(new ValidateSchemaCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testFailsValidationUnknownDirective(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: ID @unknown
        }
        GRAPHQL;
        $tester = $this->commandTester(new ValidateSchemaCommand());

        $this->expectException(DirectiveException::class);
        $tester->execute([]);
    }

    /** @return never */
    public function testFailsValidationDirectiveInWrongLocation(): void
    {
        $this->markTestSkipped('This validation needs to be in the upstream webonyx/graphql-php validation');

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query @field {
            foo: ID @eq
        }
        GRAPHQL;
        $tester = $this->commandTester(new ValidateSchemaCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
