<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithDirectiveTest extends DBTestCase
{
    /**
     * The currently authenticated user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * The user's tasks.
     *
     * @var \Illuminate\Support\Collection<\Tests\Utils\Models\Task>
     */
    protected $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 3)->create([
            'user_id' => $this->user->getKey(),
        ]);

        $this->be($this->user);
    }

    /**
     * @test
     */
    public function itCanQueryARelationship(): void
    {
        $this->schema = '
        type User {
            task_count_string: String!
                @with(relation: "tasks")
                @method(name: "getTaskCountAsString")
        }
        
        type Query {
            user: User @auth
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = auth()->user();

        $this->assertFalse(
            $user->relationLoaded('tasks')
        );

        $this->graphQL('
        {
            user {
                task_count_string
            }
        }
        ')->assertJsonFragment([
            'task_count_string' => 'User has 3 tasks.',
        ]);

        $this->assertCount(
            3,
            $user->tasks
        );
    }
}
