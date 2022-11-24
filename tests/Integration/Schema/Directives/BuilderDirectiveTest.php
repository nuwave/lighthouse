<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class BuilderDirectiveTest extends DBTestCase
{
    public function testCallsCustomBuilderMethod(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(
                limit: Int @builder(method: "' . $this->qualifyTestResolver('limit') . '")
            ): [User!]! @all
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(limit: 1) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testCallsCustomBuilderMethodOnFieldCheckWithArgs(): void
    {
        $mock = \Mockery::mock($this);
        app()->instance(__CLASS__, $mock);
        $mock->shouldReceive('limit')->once()->withArgs(function (Builder $builder, $value, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
            $this->assertIsNumeric($value);
            $this->assertEquals(1, $value);
            $this->assertEquals([
                'arg1' => 'Hello',
                'arg2' => 'World',
            ], $args);

            return true;
        })->andReturn(User::query());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(arg1 : String, arg2 : String): [User!]! @all @builder(method: "' . $this->qualifyTestResolver('limit') . '" value: 1)
        }

        type User {
            id: ID
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(arg1: "Hello", arg2: "World") {
                id
            }
        }
        ');
    }

    public function testCallsCustomBuilderMethodOnFieldCheckWithoutArgs(): void
    {
        $mock = \Mockery::mock($this);
        app()->instance(__CLASS__, $mock);
        $mock->shouldReceive('limit')->once()->withArgs(function (Builder $builder, $value, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
            $this->assertIsNumeric($value);
            $this->assertEquals(1, $value);
            $this->assertEquals([], $args);

            return true;
        })->andReturn(User::query());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(arg1 : String, arg2 : String): [User!]! @all @builder(method: "' . $this->qualifyTestResolver('limit') . '" value: 1)
        }

        type User {
            id: ID
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ');
    }

    public function testCallsCustomBuilderMethodOnFieldWithValue(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]! @all @builder(method: "' . $this->qualifyTestResolver('limit') . '" value: 1)
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testCallsCustomBuilderMethodOnFieldWithoutValue(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]! @all @builder(method: "' . $this->qualifyTestResolver('limit') . '")
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  int|null  $value
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function limit(object $builder, ?int $value): object
    {
        return $builder->limit($value ?: 2);
    }
}
