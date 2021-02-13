<?php

namespace Tests\Integration\Federation;

use Nuwave\Lighthouse\Exceptions\FederationException;
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
            ]
        ])->assertJson([
            'data' => [
                '_entities' => [
                    $foo,
                ]
            ]
        ]);
    }

    public function testThrowsWhenNoEntityResolverIsFound(): void
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

        $this->expectException(FederationException::class);
        $this->graphQL(/** @lang GraphQL */ '
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
    }
}
