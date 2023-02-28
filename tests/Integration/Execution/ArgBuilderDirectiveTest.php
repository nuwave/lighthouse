<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class ArgBuilderDirectiveTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
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

        $user = $users->first();
        assert($user instanceof User);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(id: $id) {
                    id
                }
            }
            ', [
                'id' => $user->id,
            ])
            ->assertJsonCount(2, 'data.users');
    }

    public function testAttachInFilterToQuery(): void
    {
        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        factory(User::class, 3)->create();

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

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
        assert($user1 instanceof User);

        factory(User::class, 3)->create();

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

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

        $user = $users->first();
        assert($user instanceof User);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($userId: Int) {
                users(id: $userId) {
                    id
                }
            }
            ', [
                'userId' => $user->id,
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
        assert($user instanceof User);
        $user->created_at = now()->subDay();
        $user->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($between: [String!]!) {
            users(
                createdBetween: $between
            ) {
                id
            }
        }
        ', [
            'between' => [
                now()->subDay()->startOfDay()->format('Y-m-d H:i:s'),
                now()->subDay()->endOfDay()->format('Y-m-d H:i:s'),
            ],
        ])->assertJsonCount(1, 'data.users');
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
        assert($user instanceof User);
        $user->created_at = now()->subDay();
        $user->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($start: String!, $end: String!) {
            users(
                created: {
                    start: $start
                    end: $end
                }
            ) {
                id
            }
        }
        ', [
            'start' => now()->subDay()->startOfDay()->format('Y-m-d H:i:s'),
            'end' => now()->subDay()->endOfDay()->format('Y-m-d H:i:s'),
        ])->assertJsonCount(1, 'data.users');
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
        assert($user instanceof User);
        $user->created_at = now()->subDay();
        $user->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($between: [String!]!) {
            users(
                notCreatedBetween: $between
            ) {
                id
            }
        }
        ', [
            'between' => [
                now()->subDay()->startOfDay()->format('Y-m-d H:i:s'),
                now()->subDay()->endOfDay()->format('Y-m-d H:i:s'),
            ],
        ])->assertJsonCount(2, 'data.users');
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

        $oneYearAgo = now()->subYear();

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $user->created_at = $oneYearAgo;
        $user->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($created_at: String!) {
            users(created_at: $created_at) {
                id
            }
        }
        ', [
            'created_at' => $oneYearAgo->format('Y'),
        ])->assertJsonCount(1, 'data.users');
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

        $user = $users->first();
        assert($user instanceof User);

        $this->graphQL(/** @lang GraphQL */ '
        query ($name: String) {
            users(name: $name) {
                id
            }
        }
        ', [
            'name' => $user->name,
        ])->assertJsonCount(1, 'data.users');
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
        assert($onlyTitle instanceof Post);
        $onlyTitle->title = $content;
        $onlyTitle->save();

        $onlyBody = factory(Post::class)->make();
        assert($onlyBody instanceof Post);
        $onlyBody->body = $content;
        $onlyBody->save();

        $titleAndBody = factory(Post::class)->make();
        assert($titleAndBody instanceof Post);
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
