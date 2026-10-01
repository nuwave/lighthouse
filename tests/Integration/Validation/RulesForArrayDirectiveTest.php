<?php declare(strict_types=1);

namespace Tests\Integration\Validation;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;

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

    public function testBuiltInRuleNotShadowedByGlobalClassWithMatchingName(): void
    {
        // Like a facade alias such as `URL` or intervention/image's `Image`,
        // which matches the built-in rule case-insensitively once loaded.
        if (! class_exists('Filled')) {
            class_alias((new class {})::class, 'Filled');
        }

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                list: [String]
                    @rulesForArray(apply: ["filled"])
            ): Int
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    list: ["bar"]
                )
            }
            GRAPHQL)
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

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
