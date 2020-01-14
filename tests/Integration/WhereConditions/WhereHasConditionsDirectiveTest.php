<?php

namespace Tests\Integration\WhereConditions;

use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class WhereHasConditionsDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */'
    type User {
        id: ID!
        name: String
        email: String
    }

    type Post {
        id: ID!
        title: String
        body: String
    }

    type Company {
        id: ID!
        name: String
    }

    type Query {
        posts(
            hasUser: _ @whereHasConditions(relation: "user")
        ): [Post!]! @all

        users(
            hasCompany: _ @whereHasConditions(relation: "company")
            hasPost: _ @whereHasConditions(relation: "posts")
        ): [User!]! @all

        companies(
            hasUser: _ @whereHasConditions(relation: "users")
        ): [Company!]! @all

        whitelistedColumns(
            hasCompany: _ @whereHasConditions(relation: "company", columns: ["id", "camelCase"])
        ): [User!]! @all

        withoutRelation(
            hasCompany: _ @whereHasConditions
        ): [User!]! @all
    }
    ';

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereConditionsServiceProvider::class]
        );
    }

    public function testExistenceWithEmptyCondition(): void
    {
        factory(User::class)->create([
            'company_id' => null,
        ]);

        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {}
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testIgnoreNullCondition(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: null
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testWithoutRelationName(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            withoutRelation(
                hasCompany: {
                    column: "id"
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'withoutRelation' => [
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testOperatorOr(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    OR: [
                        {
                            column: "id"
                            value: 1
                        }
                        {
                            column: "id"
                            value: 3
                        }
                    ]
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '1',
                    ],
                    [
                        'id' => '3',
                    ],
                ],
            ],
        ]);
    }
}
