<?php

namespace Tests\Integration\WhereConstraints;

use Nuwave\Lighthouse\WhereConstraints\WhereConstraintsServiceProvider;
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

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereConstraintsServiceProvider::class]
        );
    }

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
                    operator: ">"
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
                            operator: ">"
                            value: 1
                        }
                        {
                            column: "id"
                            operator: "<"
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
