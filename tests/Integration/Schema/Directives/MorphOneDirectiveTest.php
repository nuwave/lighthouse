<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Hour;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphOneDirectiveTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * User's task.
     *
     * @var \Tests\Utils\Models\Task
     */
    protected $task;

    /**
     * Task's hour.
     *
     * @var \Tests\Utils\Models\Hour
     */
    protected $hour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->hour = $this->task
            ->hours()
            ->save(
                factory(Hour::class)->create()
            );
    }

    public function testCanResolveMorphOneRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Hour {
            id: ID!
            from: String
            to: String
        }

        type Task {
            id: ID!
            name: String!
            hour: Hour! @morphOne
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!){
            task(id: $id) {
                id
                name
                hour {
                    id
                    from
                    to
                }
            }
        }
        ', [
            'id' => $this->task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'hour' => [
                        'id' => $this->hour->id,
                        'from' => $this->hour->from,
                        'to' => $this->hour->to,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphOneWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Hour {
            id: ID!
            from: String
            to: String
        }

        type Task {
            id: ID!
            name: String!
            customHour: Hour! @morphOne(relation: "hour")
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!){
            task(id: $id) {
                id
                name
                customHour {
                    id
                    from
                    to
                }
            }
        }
        ', [
            'id' => $this->task->id,
        ])->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'customHour' => [
                        'id' => $this->hour->id,
                        'from' => $this->hour->from,
                        'to' => $this->hour->to,
                    ],
                ],
            ],
        ]);
    }
}
