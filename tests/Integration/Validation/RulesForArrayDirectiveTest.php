<?php

namespace Tests\Integration\Validation;

use Tests\TestCase;

class RulesForArrayDirectiveTest extends TestCase
{
    public function testValidatesListSize(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                list: [String]
                    @rulesForArray(apply: ["min:1"])
            ): ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    list: []
                )
            }
            ')
            ->assertGraphQLValidationKeys(['list']);
    }

    public function testValidatesListSizeOfInputObjects(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                list: [Foo]
                    @rulesForArray(apply: ["min:1"])
            ): ID
        }

        input Foo {
            bar: ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    list: []
                )
            }
            ')
            ->assertGraphQLValidationKeys(['list']);
    }
}
