<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
            id: Int
            foo: String
        }
        type Query {
            user: User @auth
        }
        ';

        $this->be($this->user);

        $result = $this->queryAndReturnResult($schema, '{ user { tasks { id } } }');

        $this->assertCount(3, array_get($result->data, 'user.tasks'));
    }

    /**
     * @test
     */
    public function itCanQueryHasManyPaginator()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type:"paginator")
            posts: [Post!]! @hasMany(type:"paginator")
        }
        type Task {
            id: Int!
        }
        type Post {
            id: Int!
        }
        type Query {
            user: User @auth
        }
        ';

        $result = $this->queryAndReturnResult($schema, '
        { user { tasks(count: 2) { paginatorInfo { total count hasMorePages } data { id } } } }
        ');

        $this->assertEquals(2, array_get($result->data, 'user.tasks.paginatorInfo.count'));
        $this->assertEquals(3, array_get($result->data, 'user.tasks.paginatorInfo.total'));
        $this->assertTrue(array_get($result->data, 'user.tasks.paginatorInfo.hasMorePages'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.data'));
    }

    /**
     * @test
     */
    public function itCanQueryHasManyRelayConnection()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type:"relay")
        }
        type Task {
            id: Int!
        }
        type Query {
            user: User @auth
        }
        ';

        $result = $this->queryAndReturnResult($schema, '
        { user { tasks(first: 2) { pageInfo { hasNextPage } edges { node { id } } } } }
        ');

        $this->assertTrue(array_get($result->data, 'user.tasks.pageInfo.hasNextPage'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.edges'));
    }

    /**
     * @test
     */
    public function itCanQueryHasManyNestedRelationships()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type:"relay")
        }
        type Task {
            id: Int!
            user: User @belongsTo
        }
        type Query {
            user: User @auth
        }
        ';

        $result = $this->queryAndReturnResult($schema, '
        { 
            user { 
                tasks(first: 2) { 
                    pageInfo { 
                        hasNextPage 
                    } 
                    edges { 
                        node { 
                            id
                            user {
                                tasks(first: 2) {
                                    edges {
                                        node {
                                            id
                                        }
                                    }
                                }
                            }
                        } 
                    } 
                } 
            } 
        }
        ');

        $this->assertTrue(array_get($result->data, 'user.tasks.pageInfo.hasNextPage'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.edges'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.edges.0.node.user.tasks.edges'));
    }

    /**
     * @test
     */
    public function itThrowsErrorWithUnknownTypeArg()
    {
        $this->expectException(DirectiveException::class);
        $schema = $this->buildSchemaWithDefaultQuery('
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type:"foo")
        }
        type Task {
            foo: String
        }');
        $type = $schema->getType('User');
        $type->config['fields']();
    }
}
