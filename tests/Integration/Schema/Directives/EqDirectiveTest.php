<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class EqDirectiveTest extends DBTestCase
{
    public function testAttachEqFilterFromFieldArgument(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(id: ID @eq): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID) {
                users(id: $id) {
                    id
                }
            }
            GRAPHQL, [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromInputObject(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(input: UserInput!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID) {
                users(
                    input: {
                        id: $id
                    }
                ) {
                    id
                }
            }
            GRAPHQL, [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromInputObjectWithinList(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(input: [UserInput!]!): [User!]! @all
        }

        input UserInput {
            id: ID @eq
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
            GRAPHQL, [
                'id' => $users->first()->getKey(),
            ])
            ->assertJsonCount(1, 'data.users');
    }

    public function testAttachEqFilterFromField(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq(key: "id", value: {$users->first()->getKey()})
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    id
                }
            }
            GRAPHQL)
            ->assertJsonCount(1, 'data.users');
    }

    public function testEqOnFieldRequiresValue(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq
        }
        GRAPHQL);
    }

    public function testEqOnFieldRequiresKey(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all @eq(value: 3)
        }
        GRAPHQL);
    }
}
