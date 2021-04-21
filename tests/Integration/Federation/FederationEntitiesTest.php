<?php

namespace Tests\Integration\Federation;

use Nuwave\Lighthouse\Federation\EntityResolverProvider;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

class FederationEntitiesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testCallsEntityResolverClass(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type Query {
          foo: Int!
        }
        ';

        $foo = [
            '__typename' => 'Foo',
            'id' => 42,
        ];

        $this->graphQL(/** @lang GraphQL */ '
        query ($_representations: [_Any!]!) {
            _entities(representations: $_representations) {
                __typename
                ... on Foo {
                    id
                }
            }
        }
        ', [
            '_representations' => [
                $foo,
            ],
        ])->assertJson([
            'data' => [
                '_entities' => [
                    $foo,
                ],
            ],
        ]);
    }

    public function testThrowsWhenTypeIsUnknown(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ '
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
        ');

        $this->assertStringContainsString(
            EntityResolverProvider::unknownTypename('Unknown'),
            $response->json('errors.0.message')
        );
    }

    public function testThrowsWhenNoKeySelectionIsSatisfied(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ '
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
        ');

        $this->assertStringContainsString(
            'Representation does not satisfy any set of uniquely identifying keys',
            $response->json('errors.0.message')
        );
    }

    public function testThrowsWhenMissingResolver(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type MissingResolver @key(fields: "id") {
          id: ID! @external
          foo: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $response = $this->graphQL(/** @lang GraphQL */ '
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
        ');

        $this->assertStringContainsString(
            EntityResolverProvider::missingResolver('MissingResolver'),
            $response->json('errors.0.message')
        );
    }
}
