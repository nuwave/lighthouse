<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * Auth user.
     *
     * @var User
     */
    protected $user;

    /**
     * User's tasks.
     *
     * @var Task
     */
    protected $task;

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->getKey(),
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToRelationship()
    {
        $schema = '
        type Task {
            user: User! @belongsTo
        }
        type User {
            foo: String!
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'](), 'user.resolve');
        $user = $resolver($this->task, []);

        $this->assertInstanceOf(User::class, $user);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToWithCustomName()
    {
        $schema = '
        type Task {
            bar: User! @belongsTo(relation:"user")
        }
        type User {
            foo: String!
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'](), 'bar.resolve');
        $user = $resolver($this->task, []);

        $this->assertInstanceOf(User::class, $user);
    }
}
