<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ArgBuilderDirectiveTest extends DBTestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User>
     */
    protected $users;

    protected $schema = /** @lang GraphQL */ '
    type User {
        id: ID!
        name: String
        email: String
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = factory(User::class, 5)->create();
    }

    public function testCanAttachEqFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(id: ID @eq): [User!]! @all
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
                'id' => $this->users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testCanAttachEqFilterFromInputObject(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(input: UserInput!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(
                    input: {
                        id: $id
                    }
                ) {
                    id
                }
            }
            ', [
                'id' => $this->users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testCanAttachEqFilterFromInputObjectWithinList(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(input: [UserInput!]!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(
                    input: [
                        {
                            id: $id
                        }
                    ]
                ) {
                    id
                }
            }
            ', [
                'id' => $this->users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testCanAttachNeqFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
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
                'id' => $this->users->first()->getKey(),
            ])
            ->assertJsonCount(4, 'data.users');
    }

    public function testCanAttachInFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
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
                    $this->users->first()->getKey(),
                    $this->users->last()->getKey(),
                ],
            ])
            ->assertJsonCount(2, 'data.users');
    }

    public function testCanAttachNotInFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(exclude: [Int] @notIn(key: "id")): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();
        $user2 = $this->users->last()->getKey();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(exclude: ['.$user1.', '.$user2.']) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    public function testCanAttachWhereFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(id: Int @where(operator: ">")): [User!]! @all
        }
        ';

        /** @var \Tests\Utils\Models\User $user1 */
        $user1 = $this->users->first();

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($userId: Int) {
                users(id: $userId) {
                    id
                }
            }
            ', [
                'userId' => $user1->id,
            ])
            ->assertJsonCount(4, 'data.users');
    }

    public function testCanAttachTwoWhereFilterWithTheSameKeyToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                start: Int @where(key: "id", operator: ">")
                end: Int @where(key: "id", operator: "<")
            ): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();
        $user2 = $this->users->last()->getKey();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                start: '.$user1.'
                end: '.$user2.'
            ) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    public function testCanAttachWhereBetweenFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                createdBetween: [String!]! @whereBetween(key: "created_at")
            ): [User!]! @all
        }
        ';

        $user = $this->users[0];
        $user->created_at = now()->subDay();
        $user->save();

        $user = $this->users[1];
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                createdBetween: ["'.$start.'", "'.$end.'"]
            ) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    public function testCanUseInputObjectsForWhereBetweenFilter(): void
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

        $user = $this->users[0];
        $user->created_at = now()->subDay();
        $user->save();

        $user = $this->users[1];
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                created: {
                    start: "'.$start.'"
                    end: "'.$end.'"
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    public function testCanAttachWhereNotBetweenFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                notCreatedBetween: [String!]! @whereNotBetween(key: "created_at")
            ): [User!]! @all
        }
        ';

        $user = $this->users[0];
        $user->created_at = now()->subDay();
        $user->save();

        $user = $this->users[1];
        $user->created_at = now()->subDay();
        $user->save();

        $start = now()->subDay()->startOfDay()->format('Y-m-d H:i:s');
        $end = now()->subDay()->endOfDay()->format('Y-m-d H:i:s');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                notCreatedBetween: ["'.$start.'", "'.$end.'"]
            ) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    public function testCanAttachWhereClauseFilterToQuery(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            users(
                created_at: String! @where(clause: "whereYear")
            ): [User!]! @all
        }
        ';

        $user = $this->users[0];
        $user->created_at = now()->subYear();
        $user->save();

        $user = $this->users[1];
        $user->created_at = now()->subYear();
        $user->save();

        $year = now()->subYear()->format('Y');

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(created_at: "'.$year.'") {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
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

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(name: "'.$this->users->first()->name.'") {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }
}
