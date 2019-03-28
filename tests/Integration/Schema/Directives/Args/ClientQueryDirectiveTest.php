<?php

namespace Tests\Integration\Schema\Directives\Args;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ClientQueryDirectiveTest extends DBTestCase
{
    protected $schema = '
    type User {
        id: ID!
        name: String
        email: String
    }
    
    type Query {
        users(where: WhereConstraint @clientQuery): [User!]! @all
    }
    ';

    /**
     * @test
     */
    public function itCanAddASingleWhereFilter(): void
    {
        factory(User::class, 2)->create();

        $this->query('
        {
            users(
                where: {
                    column: "id"
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }
}
