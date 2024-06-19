<?php declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanQueryDirectiveDBTest extends DBTestCase
{
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
                @canQuery(ability: "view")
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
            user(id: ID! @whereKey): User
                @canQuery(ability: "view", query: true)
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
                @canQuery(ability: "delete")
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
            task(id: ID! @whereKey): Task
                @canQuery(ability: "adminOnly")
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
