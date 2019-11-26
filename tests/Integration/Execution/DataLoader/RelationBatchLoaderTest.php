<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Support\Facades\DB;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class RelationBatchLoaderTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        name: String
    }

    type User {
        name: String
        email: String
        tasks: [Task] @hasMany
    }

    type Query {
        user(id: ID! @eq): User @find
        users: [User!]! @all
    }
    ';

    /** @var \Illuminate\Support\Collection<User> */
    protected $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });
    }

    public function testCanResolveBatchedFieldsFromBatchedRequests(): void
    {
        $query = /** @lang GraphQL */ '
        query User($id: ID!) {
            user(id: $id) {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $this
            ->postGraphQL([
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $this->users[0]->getKey(),
                    ],
                ],
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $this->users[1]->getKey(),
                    ],
                ],
            ])
            ->assertJsonCount(2)
            ->assertJsonCount(3, '0.data.user.tasks')
            ->assertJsonCount(3, '1.data.user.tasks');
    }

    /**
     * @dataProvider batchloadRelationsSetting
     *
     * @param  bool  $batchloadRelations
     * @param  int  $expectedQueryCount
     */
    public function testBatchloadRelations(bool $batchloadRelations, int $expectedQueryCount): void
    {
        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    email
                    tasks {
                        name
                    }
                }
            }
            ')
            ->assertJsonCount(2, 'data.users')
            ->assertJsonCount(3, 'data.users.1.tasks')
            ->assertJsonCount(3, 'data.users.0.tasks');

        $this->assertSame($expectedQueryCount, $queryCount);
    }

    public function batchloadRelationsSetting(): array
    {
        return [
            [true, 2],
            [false, 3]
        ];
    }
}
