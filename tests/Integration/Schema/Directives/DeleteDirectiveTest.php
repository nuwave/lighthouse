<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        $this->assertInstanceOf(User::class, $user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID! @whereKey): User @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
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
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID! @whereKey): User @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            deleteUser(id: "non-existing") {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'deleteUser' => null,
            ],
        ]);
    }

    public function testDeletesMultipleUsersByIDAndReturnsThem(): void
    {
        $users = factory(User::class, 2)->create();
        $this->assertInstanceOf(EloquentCollection::class, $users);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]! @whereKey): [User!]! @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($ids: [ID!]!) {
            deleteUsers(ids: $ids) {
                id
            }
        }
        GRAPHQL, [
            'ids' => $users->pluck('id'),
        ])->assertJsonCount(2, 'data.deleteUsers');

        $this->assertCount(0, User::all());
    }

    public function testDeleteByNonPrimaryKey(): void
    {
        $foo = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $foo);
        $foo->name = 'foo';
        $foo->save();

        $bar = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $bar);
        $bar->name = 'bar';
        $bar->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(name: String! @eq): [User!]! @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($name: String!) {
            deleteUsers(name: $name) {
                id
            }
        }
        GRAPHQL, [
            'name' => $foo->name,
        ])->assertJson([
            'data' => [
                'deleteUsers' => [
                    [
                        'id' => $foo->id,
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, User::all());
    }

    public function testDeleteWithScopes(): void
    {
        $named = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $named);
        $named->name = 'foo';
        $named->save();

        $unnamed = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $unnamed);
        $unnamed->name = null;
        $unnamed->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID! @whereKey): User @delete(scopes: ["named"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $named->id,
        ])->assertJson([
            'data' => [
                'deleteUser' => [
                    'id' => $named->id,
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $unnamed->id,
        ])->assertJson([
            'data' => [
                'deleteUser' => null,
            ],
        ]);
    }

    public function testDeletesMultipleNonExisting(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]! @whereKey): [User!]! @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            deleteUsers(ids: ["non-existing"]) {
                id
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'deleteUsers' => [],
            ],
        ]);
    }

    public function testDeletesMultipleEmptyInput(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUsers(ids: [ID!]! @whereKey): [User!]! @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            deleteUsers(ids: []) {
                id
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'deleteUsers' => [],
            ],
        ]);
    }

    public function testDeleteRequiresAtLeastOneArgument(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID @whereKey, email: String @eq): User @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            deleteUser {
                id
            }
        }
        GRAPHQL)->assertGraphQLError(ModifyModelExistenceDirective::wouldModifyAll());
    }

    public function testDoesNotAcceptArgumentWithoutArgBuilderDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID): User @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        GRAPHQL)->assertGraphQLError(ModifyModelExistenceDirective::wouldModifyAll());
    }

    public function testRequiresRelationWhenUsingAsArgResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            updateUser(deleteTasks: Tasks @delete): User @update
        }

        type User {
            id: ID!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY);
    }

    public function testUseNestedArgResolverDelete(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $tasks = factory(Task::class, 2)->make();
        foreach ($tasks as $task) {
            $this->assertInstanceOf(Task::class, $task);
            $task->user()->associate($user);
            $task->save();
        }

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!, $deleteTasks: [ID!]!) {
            updateUser(id: $id, deleteTasks: $deleteTasks) {
                id
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(Task::class, $task);

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->task()->associate($task);
        $post->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            updateTask(id: $id, deletePost: false) {
                id
                post {
                    id
                }
            }
        }
        GRAPHQL, [
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            updateTask(id: $id, deletePost: true) {
                id
                post {
                    id
                }
            }
        }
        GRAPHQL, [
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

    public function testDeleteHasOneThroughNestedArgResolverFiresModelEvents(): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $task->post()->save($post);

        $deletingCalled = false;
        Post::deleting(static function () use (&$deletingCalled): void {
            $deletingCalled = true;
        });

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateTask(
                id: ID!
                deletePost: Boolean @delete(relation: "post")
            ): Task! @update
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
            updateTask(id: $id, deletePost: true) {
                id
            }
        }
        ', [
            'id' => $task->id,
        ])->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => "{$task->id}",
                ],
            ],
        ]);

        $this->assertTrue(
            $deletingCalled,
            'Deleting the related model must trigger model events.',
        );
    }

    public function testDeleteBelongsToThroughNestedArgResolver(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            updateTask(id: $id, deleteUser: true) {
                id
                user {
                    id
                }
            }
        }
        GRAPHQL, [
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

    public function testDeleteBelongsToThroughNestedArgResolverFiresModelEvents(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $deletingCalled = false;
        User::deleting(static function () use (&$deletingCalled): void {
            $deletingCalled = true;
        });

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
            }
        }
        ', [
            'id' => $task->id,
        ])->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => "{$task->id}",
                ],
            ],
        ]);

        $this->assertTrue(
            $deletingCalled,
            'Deleting the related model must trigger model events.',
        );
    }

    public function testDeletingReturnsFalseTriggersException(): void
    {
        User::deleting(static fn (): bool => false);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Mutation {
            deleteUser(id: ID! @whereKey): User @delete
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            deleteUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertGraphQLError(ModifyModelExistenceDirective::couldNotModify($user));
    }
}
