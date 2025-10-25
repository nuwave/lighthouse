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

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad
        }
        ');
    }

    public function testLazyLoadRelationArgumentMustNotBeEmptyList(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad(relations: [])
        }
        ');
    }

    public function testLazyLoadRelationsOnConnections(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

        $this->schema = /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 1) {
                data {
                    tasksLoaded
                }
            }
        }
        ')->assertJson([
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
