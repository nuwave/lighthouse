<?php

namespace Tests\Integration\WhereConditions;

use Nuwave\Lighthouse\WhereConditions\SQLOperator;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsDirective;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
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

    protected function getPackageProviders($app): array
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

    public function testAddsNestedAndOr(): void
    {
        factory(User::class, 5)->create();

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
                            OR: [
                                {
                                    column: "id"
                                    value: 2
                                }
                                {
                                    column: "id"
                                    value: 3
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
                        'id' => '2',
                    ],
                    [
                        'id' => '3',
                    ],
                ],
            ],
        ]);
    }

    public function testHasMixed(): void
    {
        factory(User::class, 9)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 1)->create([
            'post_id' => 3,
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 7,
            'user_id' => 2,
            'comment' => 'test',
        ]);

        factory(Comment::class, 5)->create([
            'post_id' => 9,
            'user_id' => 2,
            'comment' => 'test',
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 11,
            'user_id' => 1,
            'comment' => 'test',
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 14,
            'user_id' => 2,
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 15,
            'user_id' => 2,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    column: "id"
                    operator: LT
                    value: 8
                    AND: [{
                        column: "id"
                        operator: GT
                        value: 2
                        HAS: {
                            relation  : "posts.comments"
                            operator  : GT
                            amount    : 4
                            condition : {
                               column : "comment",
                               value  : "test"
                               HAS    : {
                                    relation  : "user.posts.comments"
                               }
                            }
                        }
                    }]
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '5',
                    ],
                ],
            ],
        ]);
    }

    public function testHasRelation(): void
    {
        factory(User::class, 5)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 1)->create([
            'post_id' => 3,
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 7,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation: "posts.comments"
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
                        'id' => '2',
                    ],
                    [
                        'id' => '4',
                    ],
                ],
            ],
        ]);
    }

    public function testHasAmount(): void
    {
        factory(User::class, 5)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 5)->create([
            'post_id' => 3,
        ]);

        factory(Comment::class, 2)->create([
            'post_id' => 7,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation : "posts.comments"
                        amount   : 3
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
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testHasOperator(): void
    {
        factory(User::class, 5)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 5)->create([
            'post_id' => 3,
        ]);

        factory(Comment::class, 6)->create([
            'post_id' => 7,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation : "posts.comments"
                        amount   : 5
                        operator : EQ
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
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testHasCondition(): void
    {
        factory(User::class, 5)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 1)->create([
            'post_id' => 3,
            'comment' => 'test',
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 7,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation  : "posts.comments"
                        condition : {
                           column : "comment",
                           value  : "test"
                        }
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
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testHasRecursive(): void
    {
        factory(User::class, 7)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 1)->create([
            'post_id' => 7,
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 11,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    column: "id"
                    operator: LT
                    value: 6
                    AND: [{
                        HAS: {
                            relation  : "posts.comments"
                        }
                    }]
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '4',
                    ],
                ],
            ],
        ]);
    }

    public function testHasNested(): void
    {
        factory(User::class, 5)->create()->each(function ($user) {
            $user->posts()->saveMany(factory(Post::class, 2)->create());
        });

        factory(Comment::class, 1)->create([
            'post_id' => 3,
            'user_id' => 3,
        ]);

        factory(Comment::class, 1)->create([
            'post_id' => 7,
            'user_id' => 2,
        ]);

        factory(Task::class, 1)->create([
            'user_id' => 2,
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation  : "posts.comments"
                        condition : {
                           HAS: {
                               relation  : "user.posts.comments"
                               condition : {
                                   HAS: {
                                       relation  : "post.user.tasks"
                                   }
                               }
                           }
                        }
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
                        'id' => '4',
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

        $this->assertNotNull($enum);
        /** @var array<string, mixed> $enum */
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
