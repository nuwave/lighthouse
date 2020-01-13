<?php

namespace Tests\Integration\WhereHasConditions;

use Nuwave\Lighthouse\WhereConstraints\SQLOperator;
use Nuwave\Lighthouse\WhereHasConditions\WhereHasConditionsDirective;
use Nuwave\Lighthouse\WhereHasConditions\WhereHasConditionsServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;

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
    }
    ';

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereHasConditionsServiceProvider::class]
        );
    }

    public function testDefaultsToWhereEqual(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    column: "id"
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testOverwritesTheOperator(): void
    {
        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
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

    public function testOperatorIn(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    column: "id",
                    operator: IN
                    value: [2, 5]
                }
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
                    [
                        'id' => '5',
                    ],
                ],
            ],
        ]);
    }

    public function testOperatorIsNull(): void
    {
        factory(Post::class)->create([
            'body' => null,
        ]);
        factory(Post::class)->create([
            'body' => 'foobar',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasPost: {
                    column: "body",
                    operator: IS_NULL
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
                ],
            ],
        ]);
    }

    public function testOperatorNotNull(): void
    {
        factory(Post::class)->create([
            'body' => null,
        ]);
        factory(Post::class)->create([
            'body' => 'foobar',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasPost: {
                    column: "body",
                    operator: IS_NOT_NULL
                }
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

    public function testOperatorNotBetween(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    column: "id",
                    operator: NOT_BETWEEN
                    value: [2, 4]
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
                        'id' => '5',
                    ],
                ],
            ],
        ]);
    }

    public function testAddsNestedAnd(): void
    {
        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
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

    public function testAddsNestedOr(): void
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
                        {
                            OR: [
                                {
                                    column: "id"
                                    value: 5
                                }
                            ]

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
                    [
                        'id' => '5',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsInvalidColumnName(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    AND: [
                        {
                            column: "Robert\'); DROP TABLE Students;--"
                            value: "https://xkcd.com/327/"
                        }
                    ]
                }
            ) {
                id
            }
        }
        ')->assertJsonFragment([
            'message' => WhereHasConditionsDirective::invalidColumnName("Robert'); DROP TABLE Students;--"),
        ]);
    }

    public function testQueriesEmptyStrings(): void
    {
        factory(User::class, 3)->create();

        $userNamedEmptyString = factory(User::class)->create([
            'name' => '',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            companies(
                hasUser: {
                    column: "name"
                    value: ""
                }
            ) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'companies' => [
                    [
                        'id' => $userNamedEmptyString->company_id,
                    ],
                ],
            ],
        ]);
    }

    public function testCanQueryForNull(): void
    {
        factory(User::class, 3)->create();

        $userNamedNull = factory(User::class)->create([
            'name' => null,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            companies(
                hasUser: {
                    column: "name"
                    value: null
                }
            ) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'companies' => [
                    [
                        'id' => $userNamedNull->company_id,
                    ],
                ],
            ],
        ]);
    }

    public function testRequiresAValueForAColumn(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasPost: {
                    column: "no_value"
                }
            ) {
                id
            }
        }
        ')->assertJsonFragment([
            'message' => SQLOperator::missingValueForColumn('no_value'),
        ]);
    }

    public function testOnlyAllowsWhitelistedColumns(): void
    {
        factory(User::class)->create();

        $this->graphQL(/* @lang GraphQL */ '
        {
            whitelistedColumns(
                hasCompany: {
                    column: ID
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'whitelistedColumns' => [
                    [
                        'id' => 1,
                    ],
                ],
            ],
        ]);

        $expectedEnumName = 'WhitelistedColumnsHasCompanyColumn';
        $enum = $this->introspectType($expectedEnumName);

        $this->assertArraySubset(
            [
                'kind' => 'ENUM',
                'name' => $expectedEnumName,
                'description' => 'Allowed column names for the `hasCompany` argument on the query `whitelistedColumns`.',
                'enumValues' => [
                    [
                        'name' => 'ID',
                    ],
                    [
                        'name' => 'CAMEL_CASE',
                    ],
                ],
            ],
            $enum
        );
    }
}
