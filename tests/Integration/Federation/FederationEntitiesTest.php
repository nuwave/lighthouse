<?php declare(strict_types=1);

namespace Tests\Integration\Federation;

use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use Nuwave\Lighthouse\Federation\EntityResolverProvider;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Federation\Types\Any;
use Tests\TestCase;

final class FederationEntitiesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testCallsEntityResolverClass(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type Query {
          foo: Int!
        }
        GRAPHQL;

        $foo = [
            '__typename' => 'Foo',
            'id' => '42',
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $foo,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $foo,
                ],
            ],
        ]);
    }

    public function testCallsBatchedEntityResolverClass(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type BatchedFoo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type Query {
          foo: Int!
        }
        GRAPHQL;

        $foo1 = [
            '__typename' => 'BatchedFoo',
            'id' => '42',
        ];

        $foo2 = [
            '__typename' => 'BatchedFoo',
            'id' => '69',
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on BatchedFoo {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $foo1,
                $foo2,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $foo1,
                    $foo2,
                ],
            ],
        ]);
    }

    /** https://github.com/apollographql/apollo-federation-subgraph-compatibility/issues/70. */
    public function testMaintainsOrderOfRepresentationsInResult(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type BatchedFoo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type Query {
          foo: Int!
        }
        GRAPHQL;

        $foo1 = [
            '__typename' => 'BatchedFoo',
            'id' => '42',
        ];

        $foo2 = [
            '__typename' => 'Foo',
            'id' => '69',
        ];

        $foo3 = [
            '__typename' => 'BatchedFoo',
            'id' => '9001',
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on Foo {
                    id
                }
                ... on BatchedFoo {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $foo1,
                $foo2,
                $foo3,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $foo1,
                    $foo2,
                    $foo3,
                ],
            ],
        ]);
    }

    public function testThrowsWhenTypeIsUnknown(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    { __typename: "Unknown" }
                ]
            ) {
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            EntityResolverProvider::unknownTypename('Unknown'),
            (string) $response->json('errors.0.message'),
        );
    }

    public function testThrowsWhenRepresentationIsNotArray(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    123
                ]
            ) {
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            Any::isNotArray(),
            (string) $response->json('errors.0.message'),
        );
    }

    public function testThrowsWhenTypeIsNotString(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    { __typename: 1 }
                ]
            ) {
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            Any::typenameIsNotString(),
            (string) $response->json('errors.0.message'),
        );
    }

    public function testThrowsWhenTypeIsInvalidName(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $isValidNameError = Utils::isValidNameError('1');
        $this->assertInstanceOf(Error::class, $isValidNameError);

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    { __typename: "1" }
                ]
            ) {
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            Any::typenameIsInvalidName($isValidNameError),
            (string) $response->json('errors.0.message'),
        );
    }

    public function testThrowsWhenNoKeySelectionIsSatisfied(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    { __typename: "Foo" }
                ]
            ) {
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            'Representation does not satisfy any set of uniquely identifying keys',
            (string) $response->json('errors.0.message'),
        );
    }

    public function testThrowsWhenMissingResolver(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type MissingResolver @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            _entities(
                representations: [
                    {
                        __typename: "MissingResolver"
                        id: 1
                    }
                ]
            ) {
                ... on MissingResolver {
                    id
                }
            }
        }
        GRAPHQL);

        $this->assertStringContainsString(
            EntityResolverProvider::missingResolver('MissingResolver'),
            (string) $response->json('errors.0.message'),
        );
    }
}
