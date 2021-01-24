<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class LimitDirectiveTest extends DBTestCase
{
    public function testLimitsResults(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(limit: Int @limit): [User!]! @all
        }
        ';

        $limit = 1;
        $this->graphQL(/** @lang GraphQL */ '
        query ($limit: Int) {
            users(limit: $limit) {
                id
            }
        }
        ', [
            'limit' => $limit,
        ])->assertJsonCount($limit, 'data.users');
    }

    /**
     * TODO support this and add INPUT_FIELD_DEFINITION back as an allowed location.
     */
    public function testLimitOnInputField(): void
    {
        $this->markTestSkipped('Not implemented yet because a naive implementation would cause a performance hit on all fields.');

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        input UserFilter {
            limit: Int @limit
        }

        type Query {
            users(filter: UserFilter): [User!]! @all
        }
        ';

        $limit = 1;
        $this->graphQL(/** @lang GraphQL */ '
        query ($limit: Int) {
            users(filter: {
                limit: $limit
            }) {
                id
            }
        }
        ', [
            'limit' => $limit,
        ])->assertJsonCount($limit, 'data.users');
    }

    public function testLimitsRelations(): void
    {
        $users = factory(User::class, 2)->create();

        /** @var \Tests\Utils\Models\User $user */
        foreach ($users as $user) {
            $user->tasks()->saveMany(
                factory(Task::class, 2)->make()
            );
        }

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            tasks(limit: Int @limit): [Task!]! @hasMany
        }

        type Task {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $limit = 1;
        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($limit: Int) {
                users {
                    id
                    tasks(limit: $limit) {
                        id
                    }
                }
            }
            ', [
                'limit' => $limit,
            ])
            ->assertJsonCount($limit, 'data.users.0.tasks')
            ->assertJsonCount($limit, 'data.users.1.tasks');
    }
}
