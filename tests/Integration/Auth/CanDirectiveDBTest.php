<?php

namespace Tests\Integration\Auth;

use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanDirectiveDBTest extends DBTestCase
{
    public function testQueriesForSpecificModel(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", find: "id")
                @first
        }

        type User {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                name
            }
        }
        ', [
            'id' => $user->getKey(),
        ])->assertJson([
            'data' => [
                'user' => [
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testFailsToFindSpecificModel(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", find: "id")
                @mock
        }

        type User {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(id: "not-present") {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
            'errors' => [
                [
                    'message' => 'No query results for model [Tests\Utils\Models\User] not-present',
                ],
            ],
        ]);
    }

    public function testThrowsIfFindValueIsNotGiven(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID): User
                @can(ability: "view", find: "some.path")
                @first
        }

        type User {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertGraphQLErrorMessage(CanDirective::missingKeyToFindModel('some.path'));
    }

    public function testFindUsingNestedInputWithDotNotation(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(input: FindUserInput): User
                @can(ability: "view", find: "input.id")
                @first
        }

        type User {
            name: String!
        }

        input FindUserInput {
          id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(input: {
              id: $id
            }) {
                name
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testThrowsIfNotAuthorized(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\User $author */
        $author = factory(User::class)->create();

        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->make();
        $post->user()->associate($author);
        $post->save();

        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            post(foo: ID! @eq): Post
                @can(ability: "view", find: "foo")
                @mock
        }

        type Post {
            title: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($foo: ID!) {
            post(foo: $foo) {
                title
            }
        }
        ', [
            'foo' => $post->id,
        ])->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testHandleMultipleModels(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->make();
        $postA->user()->associate($admin);
        $postA->save();

        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->make();
        $postB->user()->associate($admin);
        $postB->save();

        $this->schema = /** @lang GraphQL */ '
        type Mutation {
            deletePosts(ids: [ID!]!): [Post!]!
                @can(ability: "delete", find: "ids")
                @delete
        }

        type Post {
            title: String!
        }
        ' . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($ids: [ID!]!) {
            deletePosts(ids: $ids) {
                title
            }
        }
        ', [
            'ids' => [$postA->id, $postB->id],
        ])->assertJson([
            'data' => [
                'deletePosts' => [
                    [
                        'title' => $postA->title,
                    ],
                    [
                        'title' => $postB->title,
                    ],
                ],
            ],
        ]);
    }

    public function testWorksWithSoftDeletes(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();
        $task->delete();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            task(id: ID! @eq): Task
                @can(ability: "adminOnly", find: "id")
                @softDeletes
                @find
        }

        type Task {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            task(id: $id, trashed: WITH) {
                name
            }
        }
        ', [
            'id' => $task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'name' => $task->name,
                ],
            ],
        ]);
    }

    public function testQueriesForSpecificModelWithQuery(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(name: String! @eq): User
                @can(ability: "view", query: true)
                @first
        }

        type User {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($name: String!) {
            user(name: $name) {
                name
            }
        }
        ', [
            'name' => $user->name,
        ])->assertJson([
            'data' => [
                'user' => [
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testFailsToFindSpecificModelWithQuery(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @eq): User
                @can(ability: "view", query: true)
                @find
        }

        type User {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(id: "not-present") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testHandleMultipleModelsWithQuery(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->make();
        $postA->user()->associate($admin);
        $postA->save();

        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->make();
        $postB->user()->associate($admin);
        $postB->save();

        $this->schema = /** @lang GraphQL */ '
        type Mutation {
            deletePosts(ids: [ID!]!): [Post!]!
                @can(ability: "delete", query: true)
                @delete
        }

        type Post {
            title: String!
        }
        ' . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($ids: [ID!]!) {
            deletePosts(ids: $ids) {
                title
            }
        }
        ', [
            'ids' => [$postA->id, $postB->id],
        ])->assertJson([
            'data' => [
                'deletePosts' => [
                    [
                        'title' => $postA->title,
                    ],
                    [
                        'title' => $postB->title,
                    ],
                ],
            ],
        ]);
    }

    public function testWorksWithSoftDeletesWithQuery(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();
        $task->delete();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            task(id: ID! @eq): Task
                @can(ability: "adminOnly", query: true)
                @softDeletes
                @find
        }

        type Task {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            task(id: $id, trashed: WITH) {
                name
            }
        }
        ', [
            'id' => $task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'name' => $task->name,
                ],
            ],
        ]);
    }
}
