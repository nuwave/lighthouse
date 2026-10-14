<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

final class NamespacedDirectiveTest extends DBTestCase
{
    public function testCRUDModelDirectives(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: UserQueries! @namespaced
        }

        type UserQueries {
            find(id: ID! @eq): User @find
            list: [User!]! @all
        }

        type Mutation {
            user: UserMutations! @namespaced
        }

        type UserMutations {
            create(name: String!): User @create
            update(id: ID!, name: String): User @update
            delete(id: ID! @whereKey): User @update
        }

        type User {
            id: ID!
            name: String!
        }
        GRAPHQL;

        $name = 'foo';
        $createUserResponse = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($name: String!) {
            user {
                create(name: $name) {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'name' => $name,
        ]);
        $createUserResponse->assertJson([
            'data' => [
                'user' => [
                    'create' => [
                        'name' => $name,
                    ],
                ],
            ],
        ]);
        $userID = $createUserResponse->json('data.user.create.id');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user {
                find(id: $id) {
                    id
                }
                list {
                    id
                }
            }
        }
        GRAPHQL, [
            'id' => $userID,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'find' => [
                        'id' => $userID,
                    ],
                    'list' => [
                        [
                            'id' => $userID,
                        ],
                    ],
                ],
            ],
        ]);

        $newName = 'bar';
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!, $name: String) {
            user {
                update(id: $id, name: $name) {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'id' => $userID,
            'name' => $newName,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'update' => [
                        'id' => $userID,
                        'name' => $newName,
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($id: ID!) {
            user {
                delete(id: $id) {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'id' => $userID,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'delete' => [
                        'id' => $userID,
                        'name' => $newName,
                    ],
                ],
            ],
        ]);
    }
}
