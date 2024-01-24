<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Tests\Utils\Types\User\NonRootClassResolver;

final class ResolverProviderTest extends TestCase
{
    public function testRootQuery(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    public function testNonRootFields(): void
    {
        $id = '123';
        $this->mockResolver(['id' => $id]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @mock
        }

        type User {
            id: ID!
            nonRootClassResolver: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                id
                nonRootClassResolver
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $id,
                    'nonRootClassResolver' => NonRootClassResolver::RESULT,
                ],
            ],
        ]);
    }

    /** @see https://github.com/graphql-rules/graphql-rules/blob/master/docs/rules/06-mutations/mutation-payload-query.md */
    public function testRootQueryMutationPayload(): void
    {
        $fooResult = 1;
        $this->mockResolver(['result' => $fooResult], 'foo');
        $barResult = 2;
        $this->mockResolver($barResult, 'bar');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: Int! @mock(key: "bar")
        }

        type Mutation {
            foo: FooResult! @mock(key: "foo")
        }

        type FooResult {
            result: Int!
            query: Query!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            foo {
                result
                query {
                    bar
                }
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'result' => $fooResult,
                    'query' => [
                        'bar' => $barResult,
                    ],
                ],
            ],
        ]);
    }
}
