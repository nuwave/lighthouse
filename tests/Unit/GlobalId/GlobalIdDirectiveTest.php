<?php

namespace Tests\Unit\GlobalId;

use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class GlobalIdDirectiveTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\GlobalId\GlobalId
     */
    protected $globalId;

    public function setUp(): void
    {
        parent::setUp();

        $this->globalId = $this->app->make(GlobalId::class);
    }

    public function testDecodesGlobalId(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String! @globalId
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => $this->globalId->encode(RootType::QUERY, Foo::THE_ANSWER),
            ],
        ]);
    }

    public function testDecodesGlobalIdOnInput(): void
    {
        $this->mockResolver(
            /**
             * @param  array<string, mixed>  $args
             */
            static function ($root, array $args): array {
                return $args['input']['bar'];
            }
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: FooInput!): [String!]! @mock
        }

        input FooInput {
            bar: String! @globalId
        }
        ';

        $globalId = $this->globalId->encode('foo', 'bar');

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($bar: String!) {
                foo(input: {
                    bar: $bar
                })
            }
            ', [
                'bar' => $globalId,
            ])
            ->assertJson([
                'data' => [
                    'foo' => $this->globalId->decode($globalId),
                ],
            ]);
    }

    public function testDecodesGlobalIdInDifferentWays(): void
    {
        $this->mockResolver(
            /**
             * @param  array<string, mixed>  $args
             */
            static function ($root, array $args): array {
                return $args;
            }
        );

        $this->schema = /** @lang GraphQL */'
        type Query {
            foo(
                type: ID! @globalId(decode: TYPE)
                id: ID! @globalId(decode: ID)
                array: ID! @globalId
            ): Foo @mock
        }

        type Foo {
            type: String!
            id: ID!
            array: [String!]!
        }
        ';

        $globalId = $this->globalId->encode('Foo', 'bar');

        $this->graphQL(/** @lang GraphQL */ "
        {
            foo(
                type: \"{$globalId}\"
                id: \"{$globalId}\"
                array: \"{$globalId}\"
            ) {
                id
                type
                array
            }
        }
        ")->assertJson([
            'data' => [
                'foo' => [
                    'type' => 'Foo',
                    'id' => 'bar',
                    'array' => [
                        'Foo',
                        'bar',
                    ],
                ],
            ],
        ]);
    }
}
