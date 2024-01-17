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

    public function testNullableArgument(): void
    {
        $this->mockResolver(static fn ($_, array $args): ?string => $args['bar'] ?? null);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @globalId): String @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(bar: null)
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testNullableResult(): void
    {
        $this->mockResolver();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String @mock @globalId
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testDecodesGlobalIdOnInput(): void
    {
        $this->mockResolver(static fn ($_, array $args): array => $args['input']['bar']);

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
        $this->mockResolver(static fn ($_, array $args): array => $args);

        $this->schema = /** @lang GraphQL */ '
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
