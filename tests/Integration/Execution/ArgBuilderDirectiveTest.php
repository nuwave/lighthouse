<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ArgBuilderDirectiveTest extends DBTestCase
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
        query ($between: [String!]!) {
            users(createdBetween: $between) {
                id
            }
        }
        ', [
            'between' => [$start, $end],
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
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        query ($created: TimeRange!) {
            users(created: $created) {
                id
            }
        }
        ', [
            'created' => [
                'start' => $start,
                'end' => $end,
            ]
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
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        query ($range: [String!]!) {
            users(notCreatedBetween: $range) {
                id
            }
        }
        ', [
            'range' => [$start, $end]
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
        $user = factory(User::class)->create();
        $user->created_at = now()->subYear();
        $user->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($at: String!) {
            users(created_at: $at) {
                id
            }
        }
        ', [
            'at' => now()->subYear()->format('Y'),
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
        $this->graphQL(/** @lang GraphQL */ '
        query ($name: String) {
            users(name: $name) {
                id
            }
        }
        ', [
            'name' => $users->first()->name
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
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                name: String
                    @where(operator: "=")
                    @where(operator: "=", key: "email")
            ): [User!]! @all
        }
        ';

        $username = 'foo@bar.baz';
        factory(User::class)->create([
            'name' => $username,
        ]);
        factory(User::class)->create([
            'email' => $username,
        ]);
        factory(User::class)->create([
            'name' => $username,
            'email' => $username,
        ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($name: String) {
                users(name: $name) {
                    id
                }
            }
            ', [
                'name' => $username,
            ])
            ->assertJsonCount(1, 'data.users');
    }
}
