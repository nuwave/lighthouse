<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class LoadRelationDirectiveTest extends DBTestCase
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

        $this->user  = factory(User::class)->create();
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
            task_count_string: String! @loadRelation(relation: "tasks") @method(name: "getTaskCountAsString")
        }
        
        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
        {
            user {
                task_count_string
            }
        }
        ');

        $tasks = auth()->user()->tasks()->count();
        $this->assertSame(3, $tasks);

        // Ensure global scopes are respected here
        $this->assertSame('User has 3 tasks.', array_get($result->data, 'user.task_count_string'));
    }
}
