<?php

namespace Tests\Integration\Schema\Directives\Args;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class WhereConstraintsDirectiveTest extends DBTestCase
{
    protected $schema = '
    type User {
        id: ID!
        name: String
        email: String
    }
    
    type Query {
        users(where: WhereConstraints @whereConstraints): [User!]! @all
    }
    ';

    /**
     * @test
     */
    public function itAddsASingleWhereFilter(): void
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

    /**
     * @test
     */
    public function itOverwritesTheOperator(): void
    {
        factory(User::class, 3)->create();

        $this->query('
        {
            users(
                where: {
                    column: "id"
                    operator: GT
                    value: 1
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
    public function itAddsNestedAnd(): void
    {
        factory(User::class, 3)->create();

        $this->query('
        {
            users(
                where: {
                    AND: [
                        {
                            column: "id"
                            operator: GT
                            value: 1
                        }
                        {
                            column: "id"
                            operator: LT
                            value: 3
                        }
                    ]
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
    public function itAddsNestedOr(): void
    {
        factory(User::class, 3)->create();

        $this->query('
        {
            users(
                where: {
                    OR: [
                        {
                            column: "id"
                            value: 1
                        }
                        {
                            column: "id"
                            value: 2
                        }
                    ]
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
    public function itAddsNestedNot(): void
    {
        factory(User::class, 3)->create();

        $this->query('
        {
            users(
                where: {
                    NOT: [
                        {
                            column: "id"
                            value: 1
                        }
                    ]
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }
}
