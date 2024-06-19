<?php declare(strict_types=1);

namespace Tests\Integration\Auth;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\ClientSafeModelNotFoundException;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanFindDirectiveDBTest extends DBTestCase
{
    public function testQueriesForSpecificModel(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canFind(ability: "view", find: "id")
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

    public function testCustomModelName(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            account(id: ID! @whereKey): Account
                @canFind(ability: "view", find: "id", model: "User")
                @first(model: "User")
        }

        type Account {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            account(id: $id) {
                name
            }
        }
        ', [
            'id' => $user->getKey(),
        ])->assertJson([
            'data' => [
                'account' => [
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
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canFind(ability: "view", find: "id")
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

    public function testThrowsCustomExceptionWhenFailsToFindModel(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolverExpects(
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canFind(ability: "view", find: "id")
                @mock
        }

        type User {
            name: String!
        }
        ';

        $this->rethrowGraphQLErrors();

        try {
            $this->graphQL(/** @lang GraphQL */ '
            {
                user(id: "not-present") {
                    name
                }
            }
            ');
        } catch (Error $error) {
            $previous = $error->getPrevious();

            $this->assertNotNull($previous);
            $this->assertInstanceOf(ClientSafeModelNotFoundException::class, $previous);
        }
    }

    public function testFailsToFindSpecificModelConcealException(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolverExpects(
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canFind(ability: "view", find: "id", action: EXCEPTION_NOT_AUTHORIZED)
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
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
    }

    public function testFailsToFindSpecificModelWithFindOrFailFalse(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(null);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canFind(ability: "view", find: "id", findOrFail: false)
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
        ')->assertExactJson([
            'data' => [
                'user' => null,
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
                @canFind(ability: "view", find: "some.path")
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
        ')->assertGraphQLError(CanDirective::missingKeyToFindModel('some.path'));
    }

    public function testFindUsingNestedInputWithDotNotation(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(input: FindUserInput!): User
                @canFind(ability: "view", find: "input.id")
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

        $author = factory(User::class)->create();
        assert($author instanceof User);

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->user()->associate($author);
        $post->save();

        $this->mockResolverExpects(
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            post(foo: ID! @whereKey): Post
                @canFind(ability: "view", find: "foo")
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
        ])->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testHandleMultipleModels(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $postA = factory(Post::class)->make();
        assert($postA instanceof Post);
        $postA->user()->associate($admin);
        $postA->save();

        $postB = factory(Post::class)->make();
        assert($postB instanceof Post);
        $postB->user()->associate($admin);
        $postB->save();

        $this->schema = /** @lang GraphQL */ '
        type Mutation {
            deletePosts(ids: [ID!]! @whereKey): [Post!]!
                @canFind(ability: "delete", find: "ids")
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

        $task = factory(Task::class)->create();
        assert($task instanceof Task);
        $task->delete();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            task(id: ID! @whereKey): Task
                @canFind(ability: "adminOnly", find: "id")
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
