<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\ModifyModelExistenceDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class DeleteDirectiveTest extends DBTestCase
{
    public function testDeletesUserAndReturnsIt(): void
    {
        factory(User::class)->create();

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'deleteUser' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, User::all());
    }

    public function testDeletesMultipleUsersAndReturnsThem(): void
    {
        factory(User::class, 2)->create();

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
        }

        type Mutation {
            deleteUsers(id: [ID!]!): [User!]! @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.deleteUsers');

        $this->assertCount(0, User::all());
    }

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
        }

        type Query {
            deleteUser(id: ID): User @delete
        }
        ');
    }

    public function testRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            deleteUser: User @delete
        }
        ');
    }

    public function testRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            deleteUser(foo: String, bar: Int): User @delete
        }
        ');
    }

    public function testRequiresRelationWhenUsingAsArgResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            updateUser(deleteTasks: Tasks @delete): User @update
        }

        type User {
            id: ID!
        }
        ');
    }

    public function testUseNestedArgResolverDelete(): void
    {
        factory(User::class)->create();
        factory(Task::class, 2)->create([
            'user_id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            updateUser(
                id: Int
                deleteTasks: [Int!]! @delete(relation: "tasks")
            ): User @update
        }

        type User {
            id: Int!
            tasks: [Task!]!
        }

        type Task {
            id: Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            updateUser(id: 1, deleteTasks: [2]) {
                id
                tasks {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateUser' => [
                    'id' => 1,
                    'tasks' => [
                        [
                            'id' => 1,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testDeleteHasOneThroughNestedArgResolver(): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();
        $task->post()->save(
            factory(Post::class)->make()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            updateTask(
                id: Int
                deletePost: Boolean @delete(relation: "post")
            ): Task @update
        }

        type Task {
            id: Int!
            post: Post
        }

        type Post {
            id: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            updateTask(id: 1, deletePost: false) {
                id
                post {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => 1,
                    'post' => [
                        'id' => 1,
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            updateTask(id: 1, deletePost: true) {
                id
                post {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => 1,
                    'post' => null,
                ],
            ],
        ]);

        $this->assertNull(Post::find(1));
    }

    public function testDeleteBelongsToThroughNestedArgResolver(): void
    {
        factory(User::class)->create();
        $task = factory(Task::class)->create([
            'user_id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            updateTask(
                id: Int
                deleteUser: Boolean @delete(relation: "user")
            ): Task @update
        }

        type Task {
            id: Int!
            user: User
        }

        type User {
            id: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            updateTask(id: 1, deleteUser: true) {
                id
                user {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => 1,
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNull(
            $task->refresh()->user_id
        );
    }

    public function testNotDeleting(): void
    {
        User::deleting(function (): bool {
            return false;
        });

        $user = factory(User::class)->create();

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => ModifyModelExistenceDirective::couldNotModify($user),
                ],
            ],
        ]);
    }
}
