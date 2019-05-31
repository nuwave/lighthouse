<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BuilderTest extends DBTestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User>
     */
    protected $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = factory(User::class, 5)->create();
        $this->schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        ';
    }

    /**
     * @test
     */
    public function itCanAttachEqFilterToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(id: ID @eq): [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users(id: '.$this->users->first()->getKey().') {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachEqFilterFromInputObject(): void
    {
        $this->schema .= '
        type Query {
            users(input: UserInput! @spread): [User!]! @all
        }
        
        input UserInput {
            id: ID @eq
        }
        ';

        $this->graphQL('
        {
            users(
                input: {
                    id: '.$this->users->first()->getKey().'
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachNeqFilterToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(id: ID @neq): [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users(id: '.$this->users->first()->getKey().') {
                id
            }
        }
        ')->assertJsonCount(4, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachInFilterToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(include: [Int] @in(key: "id")): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();
        $user2 = $this->users->last()->getKey();

        $this->graphQL('
        {
            users(include: ['.$user1.', '.$user2.']) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachNotInFilterToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(exclude: [Int] @notIn(key: "id")): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();
        $user2 = $this->users->last()->getKey();

        $this->graphQL('
        {
            users(exclude: ['.$user1.', '.$user2.']) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachWhereFilterToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(id: Int @where(operator: ">")): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();

        $this->graphQL('
        {
            users(id: '.$user1.') {
                id
            }
        }
        ')->assertJsonCount(4, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachTwoWhereFilterWithTheSameKeyToQuery(): void
    {
        $this->schema .= '
        type Query {
            users(
                start: Int @where(key: "id", operator: ">")
                end: Int @where(key: "id", operator: "<")
            ): [User!]! @all
        }
        ';

        $user1 = $this->users->first()->getKey();
        $user2 = $this->users->last()->getKey();

        $this->graphQL('
        {
            users(start: '.$user1.' end: '.$user2.') {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachWhereBetweenFilterToQuery(): void
    {
        $this->schema .= '
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

        $this->graphQL('
        {
            users(
                createdBetween: ["'.$start.'", "'.$end.'"]
            ) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itCanUseInputObjectsForWhereBetweenFilter(): void
    {
        $this->schema .= '
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

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanAttachWhereNotBetweenFilterToQuery(): void
    {
        $this->schema .= '
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

        $this->graphQL('
        {
            users(
                notCreatedBetween: ["'.$start.'", "'.$end.'"]
            ) {
                id
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    /**
     * @test
     */
    public function itCanAttachWhereClauseFilterToQuery(): void
    {
        $this->schema .= '
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

        $this->graphQL('
        {
            users(created_at: "'.$year.'") {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itOnlyProcessesFilledArguments(): void
    {
        $this->schema .= '
        type Query {
            users(
                id: ID @eq
                name: String @where(operator: "like")
            ): [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users(name: "'.$this->users->first()->name.'") {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }
}
