<?php

namespace Tests\Unit\Federation;

use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

class ExternalDirectiveTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testExternalDirectiveForwardsScalars(): void
    {
        $id = 1;

        $this->mockResolver($id);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo @key(fields: "id") {
            id: ID! @external
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'id' => $id,
                ]
            ]
        ]);
    }

    public function testExternalDirectiveFallbackToDefaultFieldResolver(): void
    {
        $foo = [
            'id' => 1,
            'someFieldWeOwn' => 'Resolved by our own service',
        ];
        $this->mockResolver($foo);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo @key(fields: "id") {
            id: ID! @external
            someFieldWeOwn: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                id
                someFieldWeOwn
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => $foo,
            ]
        ]);
    }
}
