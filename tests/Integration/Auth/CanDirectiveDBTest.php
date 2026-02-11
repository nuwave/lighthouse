<?php declare(strict_types=1);

namespace Tests\Integration\Auth;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\ClientSafeModelNotFoundException;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

/**
 * TODO remove with v7.
 */
final class CanDirectiveDBTest extends DBTestCase
{
    public function testQueriesForSpecificModel(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID @whereKey): User
                @can(ability: "view", find: "id")
                @first
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                name
            }
        }
        GRAPHQL, [
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
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID @whereKey): User
                @can(ability: "view", find: "id")
                @mock
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(id: "not-present") {
                name
            }
        }
        GRAPHQL)->assertJson([
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID @whereKey): User
                @can(ability: "view", find: "id")
                @mock
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->rethrowGraphQLErrors();

        try {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user(id: "not-present") {
                    name
                }
            }
            GRAPHQL);
        } catch (Error $error) {
            $previous = $error->getPrevious();

            $this->assertNotNull($previous);
            $this->assertInstanceOf(ClientSafeModelNotFoundException::class, $previous);
        }
    }

    public function testFailsToFindSpecificModelWithFindOrFailFalse(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(null);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID @whereKey): User
                @can(ability: "view", find: "id", findOrFail: false)
                @mock
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(id: "not-present") {
                name
            }
        }
        GRAPHQL)->assertExactJson([
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID): User
                @can(ability: "view", find: "some.path")
                @first
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertGraphQLError(CanDirective::missingKeyToFindModel('some.path'));
    }

    public function testFindUsingNestedInputWithDotNotation(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        $this->be($user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(input: {
              id: $id
            }) {
                name
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(User::class, $author);

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($author);
        $post->save();

        $this->mockResolverExpects(
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            post(foo: ID! @whereKey): Post
                @can(ability: "view", find: "foo")
                @mock
        }

        type Post {
            title: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($foo: ID!) {
            post(foo: $foo) {
                title
            }
        }
        GRAPHQL, [
            'foo' => $post->id,
        ])->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testHandleMultipleModels(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $postA = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postA);
        $postA->user()->associate($admin);
        $postA->save();

        $postB = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postB);
        $postB->user()->associate($admin);
        $postB->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            deletePosts(ids: [ID!]! @whereKey): [Post!]!
                @can(ability: "delete", find: "ids")
                @delete
        }

        type Post {
            title: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($ids: [ID!]!) {
            deletePosts(ids: $ids) {
                title
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(Task::class, $task);
        $task->delete();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            task(id: ID! @whereKey): Task
                @can(ability: "adminOnly", find: "id")
                @softDeletes
                @find
        }

        type Task {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            task(id: $id, trashed: WITH) {
                name
            }
        }
        GRAPHQL, [
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

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(name: String! @eq): User
                @can(ability: "view", query: true)
                @first
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($name: String!) {
            user(name: $name) {
                name
            }
        }
        GRAPHQL, [
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
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID! @whereKey): User
                @can(ability: "view", query: true)
                @find
        }

        type User {
            id: ID!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(id: "not-present") {
                id
            }
        }
        GRAPHQL)->assertJson([
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

        $postA = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postA);
        $postA->user()->associate($admin);
        $postA->save();

        $postB = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $postB);
        $postB->user()->associate($admin);
        $postB->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            deletePosts(ids: [ID!]! @whereKey): [Post!]!
                @can(ability: "delete", query: true)
                @delete
        }

        type Post {
            title: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($ids: [ID!]!) {
            deletePosts(ids: $ids) {
                title
            }
        }
        GRAPHQL, [
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

        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);
        $task->delete();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            task(id: ID! @whereKey): Task
                @can(ability: "adminOnly", query: true)
                @softDeletes
                @find
        }

        type Task {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            task(id: $id, trashed: WITH) {
                name
            }
        }
        GRAPHQL, [
            'id' => $task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'name' => $task->name,
                ],
            ],
        ]);
    }

    public function testChecksAgainstResolvedModelsFromPaginator(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]!
                @can(ability: "view", resolved: true)
                @paginate
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2) {
                data {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'data' => [
                        [
                            'name' => $user->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChecksAgainstRelation(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $company = factory(Company::class)->create();

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->company()->associate($company);
        $user->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            company: Company @first
        }

        type Company {
            users: [User!]!
                @can(ability: "view", resolved: true)
                @hasMany
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            company {
                users {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'company' => [
                    'users' => [
                        [
                            'name' => $user->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChecksAgainstMissingResolvedModelWithFind(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID @whereKey): User
                @can(ability: "view", resolved: true)
                @find
        }

        type User {
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(id: "not-present") {
                name
            }
        }
        GRAPHQL)->assertJson([
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
}
