<?php

namespace Nuwave\Lighthouse\Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
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
        type Task {
            foo: String
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'], 'tasks.resolve');
        $tasks = $resolver($this->user, []);

        $this->assertCount(3, $tasks);
    }

    /**
     * @test
     */
    public function itCanQueryHasManyPaginator()
    {
        $schema = '
        type User {
            tasks(first: Int! page: Int): [Task!]! @hasMany(type:"paginator")
        }
        type Task {
            foo: String
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'], 'tasks.resolve');
        $tasks = $resolver($this->user, ['first' => 2, 'page' => 2]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $tasks);
        $this->assertEquals(1, $tasks->count());
        $this->assertEquals(3, $tasks->total());
        $this->assertFalse($tasks->hasMorePages());

        // TODO: Change resolve type in schema to type UserTaskPaginator
        // w/ PaginatorInfo & UserTaskData fields
    }

    /**
     * @test
     * @group failing
     */
    public function itCanQueryHasManyRelayConnection()
    {
        $schema = '
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type:"relay")
        }
        type Task {
            foo: String
        }
        ';

        $type = schema()->register($schema)->first();
        $resolver = array_get($type->config['fields'], 'tasks.resolve');
        $tasks = $resolver($this->user, ['first' => 2]);
        dd($tasks->count(), $tasks->total());

        $this->assertInstanceOf(LengthAwarePaginator::class, $tasks);
        $this->assertEquals(2, $tasks->count());
        $this->assertEquals(3, $tasks->total());
        $this->assertTrue($tasks->hasMorePages());

        // TODO: Change resolve type in schema to type UserTaskPaginator
        // w/ PaginatorInfo & UserTaskData fields
    }

    /**
     * @test
     */
    public function itThrowsErrorWithUnknownTypeArg()
    {
        $schema = '
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type:"foo")
        }
        type Task {
            foo: String
        }
        ';

        $this->expectException(DirectiveException::class);
        $type = schema()->register($schema)->first();
    }
}
