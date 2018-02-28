<?php

namespace Nuwave\Lighthouse\Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Tests\Utils\Models\User;

class HasManyDirectiveTest extends DBTestCase
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
    public function itCanQueryHasManyRelationship()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'](), 'tasks.resolve');
        $tasks = $resolver($this->user, []);

        $this->assertCount(3, $tasks);
    }
}
