<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class LazyLoadDirectiveTest extends DBTestCase
{
    public function testLazyLoadRequiresRelationArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: ID @lazyLoad
        }
        GRAPHQL);
    }

    public function testLazyLoadRelationArgumentMustNotBeEmptyList(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: ID @lazyLoad(relations: [])
        }
        GRAPHQL);
    }

    public function testLazyLoadRelationsOnConnections(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            tasks: [Task!]!
                @lazyLoad(relations: ["user"])
                @hasMany(type: CONNECTION)
        }

        type Task {
            id: ID!
            userLoaded: Boolean! @method
        }

        type Query {
            user: User @first
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                tasks(first: 1) {
                    edges {
                        node {
                            userLoaded
                        }
                    }
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'edges' => [
                            [
                                'node' => [
                                    'userLoaded' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testLazyLoadRelationsOnPaginate(): void
    {
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            tasks: [Task!]! @hasMany
            tasksLoaded: Boolean! @method
        }

        type Task {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate @lazyLoad(relations: ["tasks"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 1) {
                data {
                    tasksLoaded
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'data' => [
                        [
                            'tasksLoaded' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
