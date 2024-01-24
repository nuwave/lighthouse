<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

final class NestDirectiveTest extends DBTestCase
{
    public function testNestDelegates(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            createUser(
                name: String
                tasks: UserTasksOperations @nest
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
            createUser(
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
        ')->assertExactJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'name' => 'Uniq',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
