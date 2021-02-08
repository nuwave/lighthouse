<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class EqDirectiveTest extends DBTestCase
{
    public function testAttachEqFilterFromFieldArgument(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(id: ID @eq): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(id: $id) {
                    id
                }
            }
            ', [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromInputObject(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(input: UserInput!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(
                    input: {
                        id: $id
                    }
                ) {
                    id
                }
            }
            ', [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromInputObjectWithinList(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(input: [UserInput!]!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                users(
                    input: [
                        {
                            id: $id
                        }
                    ]
                ) {
                    id
                }
            }
            ', [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromField(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */"
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq(key: \"id\", value: {$users->first()->getKey()})
        }
        ";

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    id
                }
            }
            ')
            ->assertJsonCount(1, 'data.users');
    }

    public function testEqOnFieldRequiresValue(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq
        }
        ');
    }

    public function testEqOnFieldRequiresKey(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq(value: 3)
        }
        ');
    }
}
