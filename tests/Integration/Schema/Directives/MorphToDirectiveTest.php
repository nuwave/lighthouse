<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Hour;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphToDirectiveTest extends DBTestCase
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
        $this->hour = $this->task->hours()->save(factory(Hour::class)->create());
    }

    public function testCanResolveMorphToRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Hour {
            id: ID!
            from: String
            to: String
            hourable: Task! @morphTo(relation: "hourable")
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            hour (
                id: ID! @eq
            ): Hour @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            hour(id: $id) {
                id
                from
                to
                hourable {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->hour->id,
        ])->assertJson([
            'data' => [
                'hour' => [
                    'id' => $this->hour->id,
                    'from' => $this->hour->from,
                    'to' => $this->hour->to,
                    'hourable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Hour {
            id: ID!
            from: String
            to: String
            customHourable: Task! @morphTo(relation: "hourable")
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            hour (
                id: ID! @eq
            ): Hour @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            hour(id: $id) {
                id
                from
                to
                customHourable {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->hour->id,
        ])->assertJson([
            'data' => [
                'hour' => [
                    'id' => $this->hour->id,
                    'from' => $this->hour->from,
                    'to' => $this->hour->to,
                    'customHourable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToUsingInterfaces(): void
    {
        $post = factory(Post::class)->create([
            'user_id' => $this->user->id,
        ]);
        $postHour = $post->hours()->save(
            factory(Hour::class)->create()
        );

        $this->schema = /** @lang GraphQL */ '
        interface Hourable {
            id: ID!
        }

        type Task implements Hourable {
            id: ID!
            name: String!
        }

        type Post implements Hourable {
            id: ID!
            title: String!
        }

        type Hour {
            id: ID!
            from: String
            to: String
            hourable: Hourable! @morphTo
        }

        type Query {
            hour (
                id: ID! @eq
            ): Hour @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        query ($taskHour: ID!, $postHour: ID!){
            taskHour: hour(id: $taskHour) {
                id
                from
                to
                hourable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postHour: hour(id: $postHour) {
                id
                from
                to
                hourable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        ", [
            'taskHour' => $this->hour->id,
            'postHour' => $postHour->id,
        ])->assertJson([
            'data' => [
                'taskHour' => [
                    'id' => $this->hour->id,
                    'from' => $this->hour->from,
                    'to' => $this->hour->to,
                    'hourable' => [
                        'id' => $this->task->id,
                        'name' => $this->task->name,
                    ],
                ],
                'postHour' => [
                    'id' => $postHour->id,
                    'from' => $postHour->from,
                    'to' => $postHour->to,
                    'hourable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }
}
