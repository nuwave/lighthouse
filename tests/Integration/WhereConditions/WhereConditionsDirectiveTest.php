<?php

namespace Tests\Integration\WhereConditions;

use Nuwave\Lighthouse\WhereConditions\SQLOperator;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsDirective;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class WhereConditionsDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type User {
        id: ID!
        name: String
        email: String
    }

    type Post {
        id: ID!
        title: String
        body: String
        parent: Post @belongsTo
    }

    type Query {
        posts(where: _ @whereConditions): [Post!]! @all
        users(where: _ @whereConditions): [User!]! @all
        whitelistedColumns(
            where: _ @whereConditions(columns: ["id", "camelCase"])
        ): [User!]! @all
        enumColumns(
            where: _ @whereConditions(columnsEnum: "UserColumn")
        ): [User!]! @all
    }

    enum UserColumn {
        ID @enum(value: "id")
        NAME @enum(value: "name")
    }
    ';

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereConditionsServiceProvider::class]
        );
    }

    public function testDefaultsToWhereEqual(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testOverwritesTheOperator(): void
    {
        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testOperatorIn(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
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
            posts(
                where: {
                    column: "body",
                    operator: IS_NULL
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'posts' => [
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
            posts(
                where: {
                    column: "body",
                    operator: IS_NOT_NULL
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'posts' => [
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
                where: {
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

    public function testAddsNestedOr(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testAddsNot(): void
    {
        $this->markTestSkipped('Kind of works, but breaks down when more nested conditions are added, see https://github.com/nuwave/lighthouse/issues/1124');
        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testAddsNestedNot(): void
    {
        $this->markTestSkipped('Not working because of limitations in Eloquent, see https://github.com/nuwave/lighthouse/issues/1124');
        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    NOT: {
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
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '3',
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
                where: {
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
            'message' => WhereConditionsDirective::invalidColumnName("Robert'); DROP TABLE Students;--"),
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
            users(
                where: {
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
                'users' => [
                    [
                        'id' => $userNamedEmptyString->id,
                        'name' => $userNamedEmptyString->name,
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
            users(
                where: {
                    column: "name"
                    value: null
                }
            ) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    [
                        'id' => $userNamedNull->id,
                        'name' => $userNamedNull->name,
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
                where: {
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

        $this->graphQL(/** @lang GraphQL */ '
        {
            whitelistedColumns(
                where: {
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

        $expectedEnumName = 'WhitelistedColumnsWhereColumn';
        $enum = $this->introspectType($expectedEnumName);

        $this->assertArraySubset(
            [
                'kind' => 'ENUM',
                'name' => $expectedEnumName,
                'description' => 'Allowed column names for the `where` argument on the query `whitelistedColumns`.',
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

    public function testCanUseColumnEnumsArg(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            enumColumns(
                where: {
                    column: ID
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'enumColumns' => [
                    [
                        'id' => 1,
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
                where: null
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
}
