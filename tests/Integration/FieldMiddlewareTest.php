<?php declare(strict_types=1);

namespace Tests\Integration;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;
use Tests\Utils\Directives\GlobalFieldMiddlewareDirective;

final class FieldMiddlewareTest extends TestCase
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
        ')->assertGraphQLErrorMessage(AuthenticationException::MESSAGE);
    }

    public function testHydratesGlobalFieldMiddleware(): void
    {
        config(['lighthouse.field_middleware' => [
            GlobalFieldMiddlewareDirective::class,
        ]]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Boolean
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => true,
            ],
        ]);
    }
}
