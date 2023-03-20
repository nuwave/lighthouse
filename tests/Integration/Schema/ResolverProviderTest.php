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
}
