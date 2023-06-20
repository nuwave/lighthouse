<?php declare(strict_types=1);

namespace Tests\Integration\Auth;

use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
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

        $user = factory(User::class)->create();
        assert($user instanceof User);

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
            $this->never(),
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

    public function testFailsToFindSpecificModelWithFindOrFailFalse(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(null);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", find: "id", findOrFail: false)
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
        ')->assertGraphQLError(CanDirective::missingKeyToFindModel('some.path'));
    }

    public function testFindUsingNestedInputWithDotNotation(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);
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

        $task = factory(Task::class)->create();
        assert($task instanceof Task);
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

        $user = factory(User::class)->create();
        assert($user instanceof User);

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
            $this->never(),
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

        $task = factory(Task::class)->create();
        assert($task instanceof Task);
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

    public function testChecksAgainstResolvedModelsFromPaginator(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]!
                @can(ability: "view", resolved: true)
                @paginate
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 2) {
                data {
                    name
                }
            }
        }
        ')->assertJson([
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
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->schema = /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            company {
                users {
                    name
                }
            }
        }
        ')->assertJson([
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

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID @eq): User
                @can(ability: "view", resolved: true)
                @find
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
}
