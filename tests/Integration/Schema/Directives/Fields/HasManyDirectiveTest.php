<?php

namespace Nuwave\Lighthouse\Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Utils\SchemaStitcher;
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

        // Need lighthouse schema to resolve PaginatorInfo type
        $types = schema()->register((new SchemaStitcher())->lighthouseSchema()."\n".$schema);
        $root = $types->first(function ($root) {
            return 'User' === $root->name;
        });
        $paginator = $types->first(function ($type) {
            return 'UserTaskPaginator' === $type->name;
        });

        $resolver = array_get($root->config['fields'], 'tasks.resolve');
        $tasks = $resolver($this->user, ['first' => 2]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $tasks);
        $this->assertEquals(2, $tasks->count());
        $this->assertEquals(3, $tasks->total());
        $this->assertTrue($tasks->hasMorePages());

        $resolver = array_get($paginator->config['fields'], 'data.resolve');
        $data = $resolver($tasks, []);
        $this->assertCount(2, $data);

        $resolver = array_get($paginator->config['fields'], 'paginatorInfo.resolve');
        $pageInfo = $resolver($tasks, []);
        $this->assertTrue($pageInfo['hasMorePages']);
        $this->assertEquals(1, $pageInfo['currentPage']);
        $this->assertEquals(2, $pageInfo['perPage']);
    }

    /**
     * @test
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

        // Need lighthouse schema to resolve PageInfo type
        $types = schema()->register((new SchemaStitcher())->lighthouseSchema()."\n".$schema);
        $root = $types->first(function ($root) {
            return 'User' === $root->name;
        });
        $connection = $types->first(function ($type) {
            return 'UserTaskConnection' === $type->name;
        });

        $resolver = array_get($root->config['fields'], 'tasks.resolve');
        $tasks = $resolver($this->user, ['first' => 2]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $tasks);
        $this->assertEquals(2, $tasks->count());
        $this->assertEquals(3, $tasks->total());
        $this->assertTrue($tasks->hasMorePages());

        $resolver = array_get($connection->config['fields'], 'edges.resolve');
        $edges = $resolver($tasks, []);
        $this->assertCount(2, $edges);

        $resolver = array_get($connection->config['fields'], 'pageInfo.resolve');
        $pageInfo = $resolver($tasks, []);
        $this->assertEquals($edges->first()['cursor'], $pageInfo['startCursor']);
        $this->assertEquals($edges->last()['cursor'], $pageInfo['endCursor']);
        $this->assertTrue($pageInfo['hasNextPage']);
        $this->assertFalse($pageInfo['hasPreviousPage']);
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
