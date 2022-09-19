<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class ArgBuilderDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type User {
        id: ID!
        name: String
        email: String
    }
    ';

    public function testAttachNeqFilterToQuery(): void
    {
        $users = factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(id: ID @neq): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(id: $id) {
                    id
                }
            }
            ', [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(2, 'data.users');
    }

    public function testAttachInFilterToQuery(): void
    {
        $user1 = factory(User::class)->create();
        factory(User::class, 3)->create();
        $user2 = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(include: [Int] @in(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [Int]) {
                users(include: $ids) {
                    id
                }
            }
            ', [
                'ids' => [
                    $user1->id,
                    $user2->id,
                ],
            ])
            ->assertJsonCount(2, 'data.users');
    }

    public function testAttachNotInFilterToQuery(): void
    {
        $user1 = factory(User::class)->create();
        factory(User::class, 3)->create();
        $user2 = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(exclude: [Int] @notIn(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [Int]) {
                users(exclude: $ids) {
                    id
                }
            }
            ', [
                'ids' => [
                    $user1->id,
                    $user2->id,
                ],
            ])
            ->assertJsonCount(3, 'data.users');
    }

    public function testAttachWhereFilterToQuery(): void
    {
        $users = factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(id: Int @where(operator: ">")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($userId: Int) {
                users(id: $userId) {
                    id
                }
            }
            ', [
                'userId' => $users->first()->getKey(),
            ])
            ->assertJsonCount(2, 'data.users');
    }

    public function testAttachTwoWhereFilterWithTheSameKeyToQuery(): void
    {
        factory(User::class, 5)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(
                start: Int @where(key: "id", operator: ">")
                end: Int @where(key: "id", operator: "<")
            ): [User!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                start: 1
                end: 5
            ) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    public function testAttachWhereBetweenFilterToQuery(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(
                createdBetween: [String!]! @whereBetween(key: "created_at")
            ): [User!]! @all
        }
        ';

        factory(User::class, 2)->create();
        $user = factory(User::class)->create();
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                createdBetween: ["' . $start . '", "' . $end . '"]
            ) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testUseInputObjectsForWhereBetweenFilter(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                created: TimeRange @whereBetween(key: "created_at")
            ): [User!]! @all
        }

        input TimeRange {
            start: String!
            end: String!
        }
        ';

        factory(User::class, 2)->create();
        $user = factory(User::class)->create();
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                created: {
                    start: "' . $start . '"
                    end: "' . $end . '"
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testAttachWhereNotBetweenFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                notCreatedBetween: [String!]! @whereNotBetween(key: "created_at")
            ): [User!]! @all
        }
        ';

        factory(User::class, 2)->create();
        $user = factory(User::class)->create();
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                notCreatedBetween: ["' . $start . '", "' . $end . '"]
            ) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    public function testAttachWhereClauseFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                created_at: String! @where(clause: "whereYear")
            ): [User!]! @all
        }
        ';

        factory(User::class, 2)->create();
        $user = factory(User::class)->create();
        $user->created_at = now()->subYear();
        $user->save();

        $year = now()->subYear()->format('Y');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(created_at: "' . $year . '") {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testOnlyProcessesFilledArguments(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                id: ID @eq
                name: String @where(operator: "like")
            ): [User!]! @all
        }
        ';

        $users = factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(name: "' . $users->first()->name . '") {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testDoesNotProcessUnusedVariable(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                ids: [ID!] @in
            ): [User!]! @all
        }
        ';

        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        query ($ids: [ID!]) {
            users(ids: $ids) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    public function testAttachMultipleWhereFiltersToQuery(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts(
                content: String
                    @where(operator: "=", key: "title")
                    @where(operator: "=", key: "body")
            ): [Post!]! @all
        }

        type Post {
            id: Int!
        }
        ';

        $content = 'foo';

        $onlyTitle = factory(Post::class)->make();
        $onlyTitle->title = $content;
        $onlyTitle->save();

        $onlyBody = factory(Post::class)->make();
        $onlyBody->body = $content;
        $onlyBody->save();

        $titleAndBody = factory(Post::class)->make();
        $titleAndBody->title = $content;
        $titleAndBody->body = $content;
        $titleAndBody->save();

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($content: String) {
                posts(content: $content) {
                    id
                }
            }
            ', [
                'content' => $content,
            ])
            ->assertExactJson([
                'data' => [
                    'posts' => [
                        [
                            'id' => $titleAndBody->id,
                        ],
                    ],
                ],
            ]);
    }
}
