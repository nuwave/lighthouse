<?php

namespace Tests\Integration;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;

class FieldMiddlewareTest extends TestCase
{
    public function testTransformsArgsBeforeCustomFieldMiddleware(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(id: ID! @trim): Foo @customFieldMiddleware
        }

        type Foo {
            id: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(id: "   foo   ") {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'id' => 'foo',
                ],
            ],
        ]);
    }

    public function testFieldMiddlewareResolveInDefinitionOrder(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @guard
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertGraphQLErrorCategory(AuthenticationException::CATEGORY);
    }
}
