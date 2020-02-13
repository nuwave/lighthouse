<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanDirectiveDBTest extends DBTestCase
{
    public function testQueriesForSpecificModel(): void
    {
        $this->be(
            new User([
                'name' => UserPolicy::ADMIN,
            ])
        );

        $user = factory(User::class)->create([
            'name' => 'foo',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", find: "id")
                @first
        }

        type User {
            id: ID!
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            user(id: {$user->getKey()}) {
                name
            }
        }
        ")->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testFailsToFindSpecificModel(): void
    {
        $this->be(
            new User([
                'name' => UserPolicy::ADMIN,
            ])
        );
        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */
            '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", find: "id")
                @mock
        }

        type User {
            id: ID!
            name: String!
        }
        ';

        $this->expectException(ModelNotFoundException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            user(id: "not-present") {
                name
            }
        }
        ');
    }

    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(
            new User([
                'name' => UserPolicy::ADMIN,
            ])
        );

        $userB = User::create([
            'name' => 'foo',
        ]);

        $postB = factory(Post::class)->create([
            'user_id' => $userB->getKey(),
            'title' => 'Harry Potter and the Half-Blood Prince',
        ]);

        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            post(foo: ID @eq): Post
                @can(ability: "view", find: "foo")
                @mock
        }

        type Post {
            id: ID!
            title: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            post(foo: {$postB->getKey()}) {
                title
            }
        }
        ")->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testCanHandleMultipleModels(): void
    {
        $user = User::create([
            'name' => UserPolicy::ADMIN,
        ]);
        $this->be($user);

        $postA = factory(Post::class)->create([
            'user_id' => $user->getKey(),
            'title' => 'Harry Potter and the Half-Blood Prince',
        ]);
        $postB = factory(Post::class)->create([
            'user_id' => $user->getKey(),
            'title' => 'Harry Potter and the Chamber of Secrets',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            deletePosts(ids: [ID!]!): [Post!]!
                @can(ability: "delete", find: "ids")
                @delete
        }

        type Post {
            id: ID!
            title: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            deletePosts(ids: [{$postA->getKey()}, {$postB->getKey()}]) {
                title
            }
        }
        ")->assertJson([
            'data' => [
                'deletePosts' => [
                    [
                        'title' => 'Harry Potter and the Half-Blood Prince',
                    ],
                    [
                        'title' => 'Harry Potter and the Chamber of Secrets',
                    ],
                ],
            ],
        ]);
    }

    public function testWorksWithSoftDeletes(): void
    {
        $this->be(
            new User([
                'name' => UserPolicy::ADMIN,
            ])
        );

        $task = factory(Task::class)->create();
        $task->delete();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            task(id: ID @eq): Task
                @can(ability: "adminOnly", find: "id")
                @softDeletes
                @find
        }

        type Task {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            task(id: {$task->getKey()}, trashed: WITH) {
                name
            }
        }
        ")->assertJson([
            'data' => [
                'task' => [
                    'name' => $task->name,
                ],
            ],
        ]);
    }
}
