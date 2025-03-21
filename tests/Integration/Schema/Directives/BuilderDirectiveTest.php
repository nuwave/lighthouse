<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class BuilderDirectiveTest extends DBTestCase
{
    public function testCallsCustomBuilderMethod(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users(
                limit: Int @builder(method: "{$this->qualifyTestResolver('limit')}")
            ): [User!]! @all
        }

        type User {
            id: ID
        }
        GRAPHQL;

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
        $this->app->instance(__CLASS__, $mock);
        $mock->shouldReceive('limit')
            ->once()
            ->withArgs(function (EloquentBuilder $builder, $value, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): bool {
                $this->assertSame(1, $value);
                $this->assertSame([
                    'arg1' => 'Hello',
                    'arg2' => 'World',
                ], $args);

                return true;
            })
            ->andReturn(User::query());

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users(arg1 : String, arg2 : String): [User!]! @all
                @builder(method: "{$this->qualifyTestResolver('limit')}" value: 1)
        }

        type User {
            id: ID
        }
        GRAPHQL;

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
        $this->app->instance(__CLASS__, $mock);
        $mock->shouldReceive('limit')
            ->once()
            ->withArgs(function (EloquentBuilder $builder, $value, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): bool {
                $this->assertIsNumeric($value);
                $this->assertSame(1, $value);
                $this->assertSame([], $args);

                return true;
            })
            ->andReturn(User::query());

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users(arg1 : String, arg2 : String): [User!]! @all
                @builder(method: "{$this->qualifyTestResolver('limit')}" value: 1)
        }

        type User {
            id: ID
        }
        GRAPHQL;

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
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users: [User!]! @all
            @builder(method: "{$this->qualifyTestResolver('limit')}" value: 1)
        }

        type User {
            id: ID
        }
        GRAPHQL;

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
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users: [User!]! @all @builder(method: "{$this->qualifyTestResolver('limit')}")
        }

        type User {
            id: ID
        }
        GRAPHQL;

        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    public function testCallsCustomBuilderMethodOnFieldWithSpecificModel(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users: [User!]! @all
        }

        type User {
            id: ID
            tasks: [Task!]! @hasMany(type: SIMPLE) @builder(method: "{$this->qualifyTestResolver('specificModel')}")
        }

        type Task {
            id: ID
        }
        GRAPHQL;

        $users = factory(User::class, 2)->create();

        foreach ($users as $user) {
            $tasks = factory(Task::class, 3)->make();
            $tasks[0]->name = $user->name;
            $user->tasks()->saveMany($tasks);
        }

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
                tasks(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        ')
            ->assertJsonCount(1, 'data.users.0.tasks.data')
            ->assertJsonCount(1, 'data.users.1.tasks.data');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User>  $builder
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User>
     */
    public static function limit(QueryBuilder|EloquentBuilder $builder, ?int $value): QueryBuilder|EloquentBuilder
    {
        return $builder->limit($value ?? 2);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User>  $builder
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User>
     */
    public static function specificModel(
        QueryBuilder|EloquentBuilder $builder,
        ?int $value,
        User $user,
    ): QueryBuilder|EloquentBuilder {
        return $builder->where('name', $user->name);
    }
}
