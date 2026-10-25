<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class InjectDirectiveTest extends DBTestCase
{
    public function testCreateFromInputObjectWithDeepInjection(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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

    public function testInjectsIntoEveryElementOfANestedListWithWildcard(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User
                @update
                @inject(context: "user.id", name: "tasks.create.*.user_id")
        }

        input UpdateUserInput {
            id: ID!
            tasks: UpdateTasksHasManyInput
        }

        input UpdateTasksHasManyInput {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($input: UpdateUserInput!) {
            updateUser(input: $input) {
                tasks {
                    name
                    user {
                        id
                    }
                }
            }
        }
        GRAPHQL, [
            'input' => [
                'id' => $user->getKey(),
                'tasks' => [
                    'create' => [
                        ['name' => 'foo'],
                        ['name' => 'bar'],
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'name' => 'foo',
                            'user' => ['id' => (string) $user->getKey()],
                        ],
                        [
                            'name' => 'bar',
                            'user' => ['id' => (string) $user->getKey()],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSkipsWildcardInjectionWhenTheTargetedListIsNotGiven(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
        }

        type User {
            id: ID
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User
                @update
                @inject(context: "user.id", name: "tasks.create.*.user_id")
        }

        input UpdateUserInput {
            id: ID!
            tasks: UpdateTasksHasManyInput
        }

        input UpdateTasksHasManyInput {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($input: UpdateUserInput!) {
            updateUser(input: $input) {
                id
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
            'input' => [
                'id' => $user->getKey(),
            ],
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => (string) $user->getKey(),
                    'tasks' => [],
                ],
            ],
        ]);
    }

    public function testThrowsWhenWildcardIsUsedOnAValueThatIsNotAList(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }

        type User {
            id: ID
        }

        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task
                @create
                @inject(context: "user.id", name: "name.*.user_id")
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Expected the value at `name` to be a list because of the `*` wildcard used in the `name` argument of `@inject`, got: string.');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
            }) {
                id
            }
        }
        GRAPHQL);
    }
}
