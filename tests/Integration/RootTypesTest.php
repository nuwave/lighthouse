<?php declare(strict_types=1);

namespace Tests\Integration;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Mutations\Foo as FooMutation;
use Tests\Utils\Queries\Foo as FooQuery;

final class RootTypesTest extends TestCase
{
    public function testCustomRootTypeNames(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        schema {
            query: RootQuery
            mutation: RootMutation
        }

        type RootQuery {
            foo: Int
        }

        extend type RootMutation {
            foo: Int
        }

        type Mutation {
            bar: Int
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            __schema {
                queryType {
                    name
                }
                mutationType {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => FooQuery::THE_ANSWER,
                '__schema' => [
                    'queryType' => [
                        'name' => 'RootQuery',
                    ],
                    'mutationType' => [
                        'name' => 'RootMutation',
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => FooMutation::THE_ANSWER,
            ],
        ]);
    }

    public function testDefaultNameIsNoRootTypeIfSchemaDefinitionOmitsIt(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        schema {
            query: Query
        }

        type Query {
            foo: Int
        }

        type Mutation {
            foo: Int
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            __schema {
                mutationType {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                '__schema' => [
                    'mutationType' => null,
                ],
            ],
        ]);
    }

    public function testProposesResolverCommandOfDefaultRootTypeName(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        schema {
            query: RootQuery
        }

        type RootQuery {
            missing: Int
        }
        GRAPHQL;

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('php artisan lighthouse:query Missing');

        $this->graphQL(/** @lang GraphQL */ '
        {
            missing
        }
        ');
    }

    public function testThrowsOnUndefinedCustomRootType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        schema {
            query: Query
            mutation: RootMutation
        }

        type Query {
            foo: Int
        }
        GRAPHQL;

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('RootMutation');

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testAddsNodeFieldToCustomQueryRoot(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        schema {
            query: RootQuery
        }

        type RootQuery {
            foo: Int
        }

        type User @node(resolver: "Tests\\Integration\\GlobalId\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        GRAPHQL);

        $queryType = $schema->getQueryType();
        $this->assertNotNull($queryType);
        $this->assertTrue($queryType->hasField('node'));
    }
}
