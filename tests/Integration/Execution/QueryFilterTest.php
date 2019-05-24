<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

/**
 * @deprecated in favour of
 * @see \Tests\Integration\Execution\BuilderTest
 */
class QueryFilterTest extends DBTestCase
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
    public function itCanAttachWhereBetweenFilterToQuery(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        
        type Query {
            users(
                start: String! @whereBetween(key: "created_at")
                end: String! @whereBetween(key: "created_at")
            ): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
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
            users(count: 5 start: "'.$start.'" end: "'.$end.'") {
                data {
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.users.data');
    }

    /**
     * @test
     */
    public function itCanAttachWhereNotBetweenFilterToQuery(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String
            email: String
        }
        
        type Query {
            users(
                start: String! @whereNotBetween(key: "created_at")
                end: String! @whereNotBetween(key: "created_at")
            ): [User!]! @paginate(model: "Tests\\\Utils\\\Models\\\User")
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
            users(count: 5 start: "'.$start.'" end: "'.$end.'") {
                data {
                    id
                }
            }
        }
        ')->assertJsonCount(3, 'data.users.data');
    }
}
