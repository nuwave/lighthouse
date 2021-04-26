<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Schema\Directives\CanDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanDirectiveDBTest extends DBTestCase
{
    public function testQueriesForSpecificModel(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

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
            id: ID!
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
            id: ID!
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
        $user = factory(User::class)->create([
            'name' => 'foo',
        ]);
        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(input: FindUserInput): User
                @can(ability: "view", find: "input.id")
                @first
        }

        type User {
            id: ID!
            name: String!
        }

        input FindUserInput {
          id: ID
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID){
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
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testThrowsIfNotAuthorized(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $userB = new User();
        $userB->name = 'foo';
        $userB->save();

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
        ")->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testHandleMultipleModels(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
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
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

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
