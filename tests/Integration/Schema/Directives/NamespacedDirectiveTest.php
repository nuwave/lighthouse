<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

final class NamespacedDirectiveTest extends DBTestCase
{
    public function testNamespaced(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            user: UserMutations! @namespaced
        }

        type UserMutations {
            create(
                name: String
                tasks:  UserTasksOperations @nest
            ): User @create
        }

        input UserTasksOperations {
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input CreateTaskInput {
            name: String
        }

        type Task {
            name: String!
        }

        type User {
            name: String
            tasks: [Task!]! @hasMany
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            user {
                create(
                    name: "foo"
                    tasks: {
                        newTask: {
                            name: "Uniq"
                        }
                    }
                ) {
                    name
                    tasks {
                        name
                    }
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'create' => [
                        'name' => 'foo',
                        'tasks' => [
                            [
                                'name' => 'Uniq',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
