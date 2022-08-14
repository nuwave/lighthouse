<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\ModifyModelExistenceDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class DeleteDirectiveTest extends DBTestCase
{
    public function testDeletesUserAndReturnsIt(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'deleteUser' => [
                    'id' => "{$user->id}",
                ],
            ],
        ]);

        $this->assertCount(0, User::all());
    }

    public function testDeleteNotFound(): void
    {
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
            deleteUser(id: "non-existing") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'deleteUser' => null,
            ],
        ]);
    }

    public function testDeletesMultipleUsersAndReturnsThem(): void
    {
        $users = factory(User::class, 2)->create();
        assert($users instanceof Collection);

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]!): [User!]! @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($ids: [ID!]!) {
            deleteUsers(ids: $ids) {
                id
            }
        }
        ', [
            'ids' => $users->pluck('id'),
        ])->assertJsonCount(2, 'data.deleteUsers');

        $this->assertCount(0, User::all());
    }

    public function testDeletesMultipleNonExisting(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]!): [User!]! @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            deleteUsers(ids: ["non-existing"]) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'deleteUsers' => [],
            ],
        ]);
    }

    public function testDeletesMultipleEmptyInput(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]!): [User!]! @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            deleteUsers(ids: []) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'deleteUsers' => [],
            ],
        ]);
    }

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
        }

        type Mutation {
            deleteUser(id: ID): User @delete
        }
        ' . self::PLACEHOLDER_QUERY);
    }

    public function testRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser: User @delete
        }
        ' . self::PLACEHOLDER_QUERY);
    }

    public function testRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(foo: String, bar: Int): User @delete
        }
        ' . self::PLACEHOLDER_QUERY);
    }

    public function testRequiresRelationWhenUsingAsArgResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Mutation {
            updateUser(deleteTasks: Tasks @delete): User @update
        }

        type User {
            id: ID!
        }
        ' . self::PLACEHOLDER_QUERY);
    }

    public function testUseNestedArgResolverDelete(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 2)->make();
        foreach ($tasks as $task) {
            assert($task instanceof Task);
            $task->user()->associate($user);
            $task->save();
        }

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateUser(
                id: ID
                deleteTasks: [ID!]! @delete(relation: "tasks")
            ): User @update
        }

        type User {
            id: ID!
            tasks: [Task!]!
        }

        type Task {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!, $deleteTasks: [ID!]!) {
            updateUser(id: $id, deleteTasks: $deleteTasks) {
                id
                tasks {
                    id
                }
            }
        }
        ', [
            'id' => $user->id,
            'deleteTasks' => [$tasks[1]->id],
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'id' => "{$user->id}",
                    'tasks' => [
                        [
                            'id' => "{$tasks[0]->id}",
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testDeleteHasOneThroughNestedArgResolver(): void
    {
        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $task->post()->save($post);

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateTask(
                id: ID
                deletePost: Boolean @delete(relation: "post")
            ): Task @update
        }

        type Task {
            id: ID!
            post: Post
        }

        type Post {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!) {
            updateTask(id: $id, deletePost: false) {
                id
                post {
                    id
                }
            }
        }
        ', [
            'id' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => "{$task->id}",
                    'post' => [
                        'id' => "{$post->id}",
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!) {
            updateTask(id: $id, deletePost: true) {
                id
                post {
                    id
                }
            }
        }
        ', [
            'id' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => "{$task->id}",
                    'post' => null,
                ],
            ],
        ]);

        $this->assertNull(Post::find($post->id));
    }

    public function testDeleteBelongsToThroughNestedArgResolver(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task = factory(Task::class)->make();
        assert($task instanceof Task);
        $task->user()->associate($user);
        $task->save();

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateTask(
                id: ID!
                deleteUser: Boolean @delete(relation: "user")
            ): Task! @update
        }

        type Task {
            id: ID!
            user: User
        }

        type User {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!) {
            updateTask(id: $id, deleteUser: true) {
                id
                user {
                    id
                }
            }
        }
        ', [
            'id' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateTask' => [
                    'id' => "{$task->id}",
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNull($task->refresh()->user_id);
        $this->assertNull(User::find($user->id));
    }

    public function testNotDeleting(): void
    {
        User::deleting(function (): bool {
            return false;
        });

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        ', [
            'id' => $user->id,
        ])->assertGraphQLError(ModifyModelExistenceDirective::couldNotModify($user));
    }
}
