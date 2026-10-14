<?php declare(strict_types=1);

namespace Tests\Unit\GlobalId;

use Nuwave\Lighthouse\GlobalId\GlobalId;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class GlobalIdDirectiveTest extends TestCase
{
    /** @var GlobalId */
    protected $globalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalId = $this->app->make(GlobalId::class);
    }

    public function testDecodesGlobalId(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: String! @globalId
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => $this->globalId->encode(RootType::QUERY, Foo::THE_ANSWER),
            ],
        ]);
    }

    public function testNullableArgument(): void
    {
        $this->mockResolver(static fn ($_, array $args): ?string => $args['bar'] ?? null);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: String @globalId): String @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo(bar: null)
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testNullableResult(): void
    {
        $this->mockResolver();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: String @mock @globalId
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testDecodesGlobalIdOnInput(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args['input']['bar']);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: FooInput!): [String!]! @mock
        }

        input FooInput {
            bar: String! @globalId
        }
        GRAPHQL;

        $globalId = $this->globalId->encode('foo', 'bar');

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($bar: String!) {
                foo(input: {
                    bar: $bar
                })
            }
            GRAPHQL, [
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
        $this->mockResolver(static fn ($_, array $args): array => $args);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $globalId = $this->globalId->encode('Foo', 'bar');

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        {
            foo(
                type: "{$globalId}"
                id: "{$globalId}"
                array: "{$globalId}"
            ) {
                id
                type
                array
            }
        }
        GRAPHQL)->assertJson([
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
