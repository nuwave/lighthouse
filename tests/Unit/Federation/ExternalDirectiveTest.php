<?php declare(strict_types=1);

namespace Tests\Unit\Federation;

use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

final class ExternalDirectiveTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testExternalDirectiveForwardsScalars(): void
    {
        $id = 1;

        $this->mockResolver($id);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Foo! @mock
        }

        type Foo @key(fields: "id") {
            id: ID! @external
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => [
                    'id' => $id,
                ],
            ],
        ]);
    }

    public function testExternalDirectiveForwardsScalarsWithinIterable(): void
    {
        $one = 1;
        $two = 2;
        $this->mockResolver([$one, $two]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foos: [Foo!]! @mock
        }

        type Foo @key(fields: "id") {
            id: ID! @external
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foos {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foos' => [
                    [
                        'id' => $one,
                    ],
                    [
                        'id' => $two,
                    ],
                ],
            ],
        ]);
    }

    public function testExternalDirectiveFallbackToDefaultFieldResolver(): void
    {
        $foo = [
            'id' => 1,
            'someFieldWeOwn' => 'Resolved by our own service',
        ];
        $this->mockResolver($foo);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Foo! @mock
        }

        type Foo @key(fields: "id") {
            id: ID! @external
            someFieldWeOwn: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                id
                someFieldWeOwn
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => $foo,
            ],
        ]);
    }
}
