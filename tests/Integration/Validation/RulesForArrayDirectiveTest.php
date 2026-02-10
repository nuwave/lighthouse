<?php declare(strict_types=1);

namespace Tests\Integration\Validation;

use Tests\TestCase;

final class RulesForArrayDirectiveTest extends TestCase
{
    public function testValidatesListSize(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                list: [String]
                    @rulesForArray(apply: ["min:1"])
            ): ID
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    list: []
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['list']);
    }

    public function testValidatesListSizeOfInputObjects(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                list: [Foo]
                    @rulesForArray(apply: ["min:1"])
            ): ID
        }

        input Foo {
            bar: ID
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    list: []
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['list']);
    }
}
