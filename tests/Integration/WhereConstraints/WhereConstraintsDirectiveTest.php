<?php

namespace Tests\Integration\WhereConstraints;

use Nuwave\Lighthouse\WhereConstraints\WhereConstraintsDirective;
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
        whitelistedColumns(
            where: WhereConstraints @whereConstraints(columns: ["id", "camelCase"])
        ): [User!]! @all
    }

    enum Operator {
        EQ @enum(value: "=")
        NEQ @enum(value: "!=")
        GT @enum(value: ">")
        GTE @enum(value: ">=")
        LT @enum(value: "<")
        LTE @enum(value: "<=")
        LIKE @enum(value: "LIKE")
        NOT_LIKE @enum(value: "NOT_LIKE")
    }
    ';

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereConstraintsServiceProvider::class]
        );
    }

    public function testAddsASingleWhereFilter(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL('
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

        $this->graphQL('
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

    public function testAddsNestedAnd(): void
    {
        factory(User::class, 3)->create();

        $this->graphQL('
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
        factory(User::class, 3)->create();

        $this->graphQL('
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

    public function testAddsNestedNot(): void
    {
        factory(User::class, 3)->create();

        $this->graphQL('
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

    public function testRejectsInvalidColumnName(): void
    {
        $this->graphQL('
        {
            users(
                where: {
                    NOT: [
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
            'message' => WhereConstraintsDirective::INVALID_COLUMN_MESSAGE,
        ]);
    }

    public function testQueriesEmptyStrings(): void
    {
        factory(User::class, 3)->create();

        $userNamedEmptyString = factory(User::class)->create([
            'name' => '',
        ]);

        $this->graphQL('
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

        $this->graphQL('
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
        $this->graphQL('
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
            'message' => WhereConstraintsDirective::missingValueForColumn('no_value'),
        ]);
    }

    public function testOnlyAllowsWhitelistedColumns(): void
    {
        factory(User::class)->create();

        $this->graphQL(/* @lang GraphQL */ '
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
}
