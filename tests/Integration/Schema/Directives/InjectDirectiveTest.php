<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class InjectDirectiveTest extends DBTestCase
{
    public function testCreateFromInputObjectWithDeepInjection(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /* @lang GraphQL */ '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID
        }

        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create @inject(context: "user.id", name: "user_id")
        }

        input CreateTaskInput {
            name: String
        }
        ';

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "foo"
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateFromInputObjectWithWildcardInjection(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /* @lang GraphQL */ '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID
            tasks: [Task!] @hasmany
        }

        type Mutation {
            updateUser(input: UpdateUserInput @spread): User
                @update
                @inject(context: "user.id", name: "tasks.create.*.user_id")
        }

        input UpdateUserInput {
            id: ID!
            tasks: CreateTaskInputMany
        }

        input CreateTaskInputMany {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
        }
        ';

        $this->graphQL('
        mutation ($input: UpdateUserInput!) {
            updateUser(input: $input) {
                tasks {
                    id
                    name
                    user {
                        id
                    }
                }
            }
        }
        ', [
            'input' => [
                'id' => $user->getKey(),
                'tasks' => [
                    'create' => [
                        [ 'name' => 'foo' ],
                        [ 'name' => 'bar' ],
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'foo',
                            'user' => [
                                'id' => '1',
                            ],
                        ],
                        [
                            'id' => '2',
                            'name' => 'bar',
                            'user' => [
                                'id' => '1',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
