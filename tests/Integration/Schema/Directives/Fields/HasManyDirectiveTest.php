<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class HasManyDirectiveTest extends DBTestCase
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
        factory(Task::class)->create([
            'user_id' => $this->user->getKey(),
            // This task should be ignored via global scope on the Task model
            'name' => 'cleaning',
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

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks {
                    id
                }
            }
        }
        ');

        $tasksWithoutGlobalScope = auth()->user()->tasks()->withoutGlobalScope('no_cleaning')->count();
        $this->assertSame(4, $tasksWithoutGlobalScope);

        // Ensure global scopes are respected here
        $this->assertCount(3, array_get($result->data, 'user.tasks'));
    }

    /**
     * @test
     */
    public function itCallsScopeWithResolverArgs()
    {
        $this->assertCount(3, $this->user->tasks);

        $schema = '
        type User {
            tasks(foo: Int): [Task!]! @hasMany(scopes: ["foo"])
        }
        
        type Task {
            id: Int
            foo: String
        }
        
        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks(foo: 2) {
                    id
                }
            }
        }
        ');

        $this->assertCount(2, array_get($result->data, 'user.tasks'));
    }

    /**
     * @test
     */
    public function itCanQueryHasManyPaginator()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator")
            posts: [Post!]! @hasMany(type: "paginator")
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

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks(count: 2) {
                    paginatorInfo {
                        total
                        count
                        hasMorePages
                    }
                    data {
                        id
                    }
                }
            }
        }
        ');

        $this->assertEquals(2, array_get($result->data, 'user.tasks.paginatorInfo.count'));
        $this->assertEquals(3, array_get($result->data, 'user.tasks.paginatorInfo.total'));
        $this->assertTrue(array_get($result->data, 'user.tasks.paginatorInfo.hasMorePages'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.data'));
    }

    /** @test */
    public function itCanQueryHasManyPaginatorWithADefaultCount()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator", defaultCount: 2)
        }
        
        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks {
                    paginatorInfo {
                        total
                        count
                        hasMorePages
                    }
                    data {
                        id
                    }
                }
            }
        }
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
            tasks: [Task!]! @hasMany(type: "relay")
        }
        
        type Task {
            id: Int!
        }
        
        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks(first: 2) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ');

        $this->assertTrue(array_get($result->data, 'user.tasks.pageInfo.hasNextPage'));
        $this->assertCount(2, array_get($result->data, 'user.tasks.edges'));
    }

    /**
     * @test
     */
    public function itCanQueryHasManyRelayConnectionWithADefaultCount()
    {
        $schema = '
        type User {
            tasks: [Task!]! @hasMany(type: "relay", defaultCount: 2)
        }
        
        type Task {
            id: Int!
        }
        
        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
        {
            user {
                tasks {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
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
            tasks: [Task!]! @hasMany(type: "relay")
        }
        
        type Task {
            id: Int!
            user: User @belongsTo
        }
        
        type Query {
            user: User @auth
        }
        ';

        $result = $this->executeQuery($schema, '
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
    public function itCanQueryHasManySelfReferencingRelationships()
    {
        $post1 = factory(Post::class)->create([
            'id' => 1,
        ]);

        $post2 = factory(Post::class)->create([
            'id' => 2,
            'parent_id' => $post1->getKey(),
        ]);

        $post3 = factory(Post::class)->create([
            'id' => 3,
            'parent_id' => $post2->getKey(),
        ]);

        $schema = '
        type Post {
            id: Int!
            parent: Post @belongsTo
        }
        
        type Query {
            posts: [Post!]! @all
        }
        ';

        $result = $this->executeQuery($schema, '
        { 
            posts {
                id
                parent {
                    id
                    parent {
                        id
                    }
                }
            } 
        }
        ');

        $posts = $result->data['posts'];

        $this->assertNull($posts[0]['parent']);

        $this->assertNotNull($posts[1]['parent']);
        $this->assertNull($posts[1]['parent']['parent']);

        $this->assertNotNull($posts[2]['parent']);
        $this->assertNotNull($posts[2]['parent']['parent']);
    }

    /**
     * @test
     */
    public function itThrowsErrorWithUnknownTypeArg()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type:"foo")
        }
        
        type Task {
            foo: String
        }
        ');

        $type = $schema->getType('User');
        $type->config['fields']();
    }
}
