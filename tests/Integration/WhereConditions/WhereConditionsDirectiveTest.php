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

        $commentOne = new Comment();
        $commentOne->post_id = 3;
        $commentOne->user_id = 1;
        $commentOne->comment = 'none';
        $commentOne->save();

        $commentTwo = new Comment();
        $commentTwo->post_id = 7;
        $commentTwo->user_id = 2;
        $commentTwo->comment = 'test';
        $commentTwo->save();

        for ($i = 0; $i < 5; $i++) {
            $commentBatch = new Comment();
            $commentBatch->post_id = 9;
            $commentBatch->user_id = 2;
            $commentBatch->comment = 'test';
            $commentBatch->save();
        }

        $commentThree = new Comment();
        $commentThree->post_id = 11;
        $commentThree->user_id = 1;
        $commentThree->comment = 'test';
        $commentThree->save();

        $commentFour = new Comment();
        $commentFour->post_id = 14;
        $commentFour->user_id = 2;
        $commentFour->comment = 'none';
        $commentFour->save();

        $commentFive = new Comment();
        $commentFive->post_id = 15;
        $commentFive->user_id = 2;
        $commentFive->comment = 'none';
        $commentFive->save();

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
                            relation: "posts.comments"
                            operator: GT
                            amount: 4
                            condition: {
                               column: "comment",
                               value: "test"
                               HAS: {
                                    relation: "user.posts.comments"
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

        $commentOne = new Comment();
        $commentOne->user_id = 1;
        $commentOne->post_id = 3;
        $commentOne->comment = 'none';
        $commentOne->save();

        $commentTwo = new Comment();
        $commentTwo->user_id = 1;
        $commentTwo->post_id = 7;
        $commentTwo->comment = 'none';
        $commentTwo->save();

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

        for ($i = 0; $i < 5; $i++) {
            $commentBatchOne = new Comment();
            $commentBatchOne->user_id = 1;
            $commentBatchOne->post_id = 3;
            $commentBatchOne->comment = 'none';
            $commentBatchOne->save();
        }

        for ($i = 0; $i < 2; $i++) {
            $commentBatchTwo = new Comment();
            $commentBatchTwo->user_id = 1;
            $commentBatchTwo->post_id = 7;
            $commentBatchTwo->comment = 'none';
            $commentBatchTwo->save();
        }

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation: "posts.comments"
                        amount: 3
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

        for ($i = 0; $i < 5; $i++) {
            $commentBatchOne = new Comment();
            $commentBatchOne->user_id = 1;
            $commentBatchOne->post_id = 3;
            $commentBatchOne->comment = 'none';
            $commentBatchOne->save();
        }

        for ($i = 0; $i < 6; $i++) {
            $commentBatchTwo = new Comment();
            $commentBatchTwo->user_id = 1;
            $commentBatchTwo->post_id = 7;
            $commentBatchTwo->comment = 'none';
            $commentBatchTwo->save();
        }

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation: "posts.comments"
                        amount: 5
                        operator: EQ
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

        $commentOne = new Comment();
        $commentOne->user_id = 1;
        $commentOne->post_id = 3;
        $commentOne->comment = 'test';
        $commentOne->save();

        $commentTwo = new Comment();
        $commentTwo->user_id = 1;
        $commentTwo->post_id = 7;
        $commentTwo->comment = 'none';
        $commentTwo->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation: "posts.comments"
                        condition: {
                           column: "comment",
                           value: "test"
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

        $commentOne = new Comment();
        $commentOne->user_id = 1;
        $commentOne->post_id = 7;
        $commentOne->comment = 'none';
        $commentOne->save();

        $commentTwo = new Comment();
        $commentTwo->user_id = 1;
        $commentTwo->post_id = 11;
        $commentTwo->comment = 'none';
        $commentTwo->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    column: "id"
                    operator: LT
                    value: 6
                    AND: [{
                        HAS: {
                            relation: "posts.comments"
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

        $commentOne = new Comment();
        $commentOne->user_id = 3;
        $commentOne->post_id = 3;
        $commentOne->comment = 'none';
        $commentOne->save();

        $commentTwo = new Comment();
        $commentTwo->user_id = 2;
        $commentTwo->post_id = 7;
        $commentTwo->comment = 'none';
        $commentTwo->save();

        $task = new Task();
        $task->name = 'test';
        $task->user_id = 2;
        $task->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                where: {
                    HAS: {
                        relation: "posts.comments"
                        condition: {
                           HAS: {
                               relation: "user.posts.comments"
                               condition: {
                                   HAS: {
                                       relation: "post.user.tasks"
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

        $expectedEnumName = 'QueryWhitelistedColumnsWhereColumn';
        $enum = $this->introspectType($expectedEnumName);

        $this->assertNotNull($enum);
        /** @var array<string, mixed> $enum */

        // TODO: Replace with dms/phpunit-arraysubset-asserts when we require PHPUnit 9 + PHP 7.3
        $this->assertSame(
            [
                'kind' => 'ENUM',
                'name' => $expectedEnumName,
                'description' => 'Allowed column names for the `where` argument on field `whitelistedColumns` on type `Query`.',
                'fields' => null,
                'inputFields' => null,
                'interfaces' => null,
                'enumValues' => [
                    [
                        'name' => 'ID',
                        'description' => null,
                        'isDeprecated' => false,
                        'deprecationReason' => null,
                    ],
                    [
                        'name' => 'CAMEL_CASE',
                        'description' => null,
                        'isDeprecated' => false,
                        'deprecationReason' => null,
                    ],
                ],
                'possibleTypes' => null,
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
