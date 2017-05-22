<?php

namespace Nuwave\Lighthouse\Tests\Queries;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Connections\TaskConnection;

class PaginationTest extends DBTestCase
{
    use GlobalIdTrait;

    /**
     * User model.
     *
     * @var User
     */
    protected $user;

    /**
     * User assigned tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $tasks;

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        $this->tasks = factory(Task::class, 6)->create([
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * @test
     */
    public function itCanPaginateConnectionWithLengthAwarePaginator()
    {
        $id = $this->encodeGlobalId(UserStubType::class, $this->user->id);
        $first = 2;
        $query = $this->getQuery($id, $first);

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserStubType::class);
        $graphql->schema()->type('task', TaskStubType::class);

        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(0)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(1)->title, array_get($edges, '1.node.title'));
        $this->assertEquals(6, array_get($data, 'data.node.tasks.pageInfo.total'));
        $this->assertEquals(2, array_get($data, 'data.node.tasks.pageInfo.count'));
        $this->assertEquals(1, array_get($data, 'data.node.tasks.pageInfo.currentPage'));
        $this->assertEquals(3, array_get($data, 'data.node.tasks.pageInfo.lastPage'));
        $this->assertNotNull(array_get($edges, '1.cursor'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(2)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(3)->title, array_get($edges, '1.node.title'));
        $this->assertEquals(6, array_get($data, 'data.node.tasks.pageInfo.total'));
        $this->assertEquals(2, array_get($data, 'data.node.tasks.pageInfo.count'));
        $this->assertEquals(2, array_get($data, 'data.node.tasks.pageInfo.currentPage'));
        $this->assertEquals(3, array_get($data, 'data.node.tasks.pageInfo.lastPage'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(4)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(5)->title, array_get($edges, '1.node.title'));
        $this->assertEquals(6, array_get($data, 'data.node.tasks.pageInfo.total'));
        $this->assertEquals(2, array_get($data, 'data.node.tasks.pageInfo.count'));
        $this->assertEquals(3, array_get($data, 'data.node.tasks.pageInfo.currentPage'));
        $this->assertEquals(3, array_get($data, 'data.node.tasks.pageInfo.lastPage'));
    }

    /**
     * @test
     */
    public function itCanPaginateConnectionWithCollection()
    {
        $id = $this->encodeGlobalId(UserStubCollectionType::class, $this->user->id);
        $first = 2;
        $query = $this->getQuery($id, $first);

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserStubCollectionType::class);
        $graphql->schema()->type('task', TaskStubType::class);

        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(0)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(1)->title, array_get($edges, '1.node.title'));
        $this->assertNotNull(array_get($edges, '1.cursor'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(2)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(3)->title, array_get($edges, '1.node.title'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(4)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(5)->title, array_get($edges, '1.node.title'));
    }

    /**
     * @test
     * @group failing
     */
    public function itCanPaginateConnectionWithConnectionType()
    {
        $id = $this->encodeGlobalId(UserStubConnectionType::class, $this->user->id);
        $first = 2;
        $query = $this->getQuery($id, $first);

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserStubConnectionType::class);
        $graphql->schema()->type('task', TaskStubType::class);

        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(0)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(1)->title, array_get($edges, '1.node.title'));
        $this->assertNotNull(array_get($edges, '1.cursor'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(2)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(3)->title, array_get($edges, '1.node.title'));

        $after = array_get($edges, '1.cursor');
        $query = $this->getQuery($id, $first, $after);
        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals($this->tasks->get(4)->title, array_get($edges, '0.node.title'));
        $this->assertEquals($this->tasks->get(5)->title, array_get($edges, '1.node.title'));
    }

    /**
     * Get connection query.
     *
     * @param  string $id
     * @param  int $first
     * @return string
     */
    protected function getQuery($id, $first, $after = null)
    {
        $args = 'first:'.$first;

        if ($after) {
            $args .= ', after:"'.$after.'"';
        }

        return '{
            node(id:"'.$id.'") {
                ... on User {
                    tasks('.$args.') {
                        pageInfo {
                            total
                            count
                            currentPage
                            lastPage
                        }
                        edges {
                            cursor
                            node {
                                title
                            }
                        }
                    }
                }
            }
        }';
    }
}

class UserStubType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'A user.',
    ];

    public function resolveById($id)
    {
        return User::find($id);
    }

    public function fields()
    {
        return [
            'name' => [
                'type' => Type::string(),
                'description' => 'Name of the user.',
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email of the user.',
            ],
            'tasks' => GraphQL::connection('task')
                ->resolve(function (User $user, array $args) {
                    return Task::whereHas('user', function ($query) use ($user) {
                        $query->where('id', $user->id);
                    })->getConnection($args);
                })->field(),
        ];
    }
}

class UserStubCollectionType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'A user.',
    ];

    public function resolveById($id)
    {
        return User::find($id);
    }

    public function fields()
    {
        return [
            'name' => [
                'type' => Type::string(),
                'description' => 'Name of the user.',
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email of the user.',
            ],
            'tasks' => GraphQL::connection('task')
                ->resolve(function (User $user, array $args) {
                    return $user->tasks->toConnection($args);
                })->field(),
        ];
    }
}

class UserStubConnectionType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'A user.',
    ];

    public function resolveById($id)
    {
        return User::find($id);
    }

    public function fields()
    {
        return [
            'name' => [
                'type' => Type::string(),
                'description' => 'Name of the user.',
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email of the user.',
            ],
            'tasks' => GraphQL::connection(new TaskConnection)->field(),
        ];
    }
}

class TaskStubType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'Task',
        'description' => 'A user task.',
    ];

    public function resolveById($id)
    {
        return Task::find($id);
    }

    public function fields()
    {
        return [
            'title' => [
                'type' => Type::string(),
                'description' => 'Title of task.',
            ],
            'description' => [
                'type' => Type::string(),
                'description' => 'Description of task.',
            ],
            'completed' => [
                'type' => Type::boolean(),
                'description' => 'Completed status.',
            ],
        ];
    }
}
