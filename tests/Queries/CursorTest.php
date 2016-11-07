<?php

namespace Nuwave\Lighthouse\Tests\Queries;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;

class CursorTest extends DBTestCase
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
            'user_id' => $this->user->id
        ]);
    }

    /**
     * @test
     */
    public function itCanEncodeCustomCursor()
    {
        $id = $this->encodeGlobalId(UserCursorType::class, $this->user->id);
        $first = 2;
        $query = $this->getQuery($id, $first);

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserCursorType::class);
        $graphql->schema()->type('task', TaskCursorType::class);

        $data = $this->executeQuery($query);
        $edges = array_get($data, 'data.node.tasks.edges');

        $this->assertCount(2, $edges);
        $this->assertEquals('foo', array_get($edges, '0.cursor'));
        $this->assertEquals('bar', array_get($edges, '1.cursor'));
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

class UserCursorType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'A user.'
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
                'description' => 'Name of the user.'
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email of the user.'
            ],
            'tasks' => GraphQL::connection('task')
                ->resolve(function (User $user, array $args) {
                    return $user->tasks->toConnection($args);
                })->cursor(function ($item, $index, $page) {
                    return $index === 0 ? 'foo' : 'bar';
                })->field()
        ];
    }
}

class TaskCursorType extends GraphQLType implements RelayType
{
    protected $attributes = [
        'name' => 'Task',
        'description' => 'A user task.'
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
                'description' => 'Title of task.'
            ],
            'description' => [
                'type' => Type::string(),
                'description' => 'Description of task.'
            ],
            'completed' => [
                'type' => Type::boolean(),
                'description' => 'Completed status.'
            ]
        ];
    }
}
