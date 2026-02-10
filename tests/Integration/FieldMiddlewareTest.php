<?php declare(strict_types=1);

namespace Tests\Integration;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;
use Tests\Utils\Directives\GlobalFieldMiddlewareDirective;

final class FieldMiddlewareTest extends TestCase
{
    public function testTransformsArgsBeforeCustomFieldMiddleware(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(id: ID! @trim): Foo @customFieldMiddleware
        }

        type Foo {
            id: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(id: "   foo   ") {
                id
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => [
                    'id' => 'foo',
                ],
            ],
        ]);
    }

    public function testFieldMiddlewareResolveInDefinitionOrder(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @guard
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testHydratesGlobalFieldMiddleware(): void
    {
        config(['lighthouse.field_middleware' => [
            GlobalFieldMiddlewareDirective::class,
        ]]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Boolean
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => true,
            ],
        ]);
    }
}
