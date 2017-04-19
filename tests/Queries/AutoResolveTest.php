<?php

namespace Nuwave\Tests\Queries;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;

class AutoResolveTest extends DBTestCase
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

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserStubType::class);
        $graphql->schema()->type('task', TaskType::class);
    }

    /**
     * @test
     */
    public function itCanAutoResolveConnection()
    {
        $data = $this->executeQuery($this->getQuery());
        $edges = array_get($data, 'data.node.tasks.edges', []);

        $this->assertCount(2, $edges);
    }

    /**
     * Get query.
     *
     * @return string
     */
    protected function getQuery()
    {
        $id = $this->encodeGlobalId(UserStubType::class, $this->user->id);

        return '{
            node(id:"'.$id.'") {
                ... on User {
                    name
                    tasks(first:2) {
                        edges {
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
            'tasks' => GraphQL::connection('task')->field('tasks'),
        ];
    }
}
