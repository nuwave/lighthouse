<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class WithDirectiveTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var User
     */
    protected $user;

    /**
     * User's tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $tasks;

    /**
     * Setup test environment.
     */
    protected function setUp()
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
    public function itCanQueryARelationship()
    {
        $schema = '
        type User {
            task_count_string: String!
                @with(relation: "tasks")
                @method(name: "getTaskCountAsString")
        }
        
        type Query {
            user: User @auth
        }
        ';

        /** @var User $user */
        $user = auth()->user();

        $this->assertFalse(
            $user->relationLoaded('tasks')
        );

        $result = $this->execute($schema, '
        {
            user {
                task_count_string
            }
        }
        ');

        $this->assertCount(
            3,
            $user->tasks
        );

        $this->assertSame(
            'User has 3 tasks.',
            array_get($result, 'data.user.task_count_string')
        );
    }
}
