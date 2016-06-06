<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use Nuwave\Lighthouse\Tests\TestCase;

class QueryParserTest extends TestCase
{
    /**
     * @test
     */
    public function itCanExtractConnections()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);
        $graphql->setQuery($this->query());

        $connections = $graphql->parser()->connections();

        $this->assertContains('tasks', $connections->keys());
        $this->assertContains('tasks.items', $connections->keys());
        $this->assertContains('friends', $connections->keys());

        $this->assertEquals(['first' => 15], $connections->get('tasks')['arguments']);
        $this->assertEquals(['first' => 10], $connections->get('tasks.items')['arguments']);
        $this->assertEquals(['first' => 5], $connections->get('friends')['arguments']);
    }

    /**
     * @test
     */
    public function itCanGetConnectionsToEagerLoad()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);
        $graphql->setQuery($this->query());

        $relationships = $graphql->eagerLoad();
        $this->assertCount(4, $relationships);
        $this->assertContains('tasks', $relationships);
        $this->assertContains('tasks.items', $relationships);
        $this->assertContains('tasks.items.assigned', $relationships);
        $this->assertContains('friends', $relationships);
    }

    /**
     * @test
     */
    public function itCanLimitDepthOfEagerLoad()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);
        $graphql->setQuery($this->query());

        $relationships = $graphql->eagerLoad(2);
        $this->assertCount(3, $relationships);
        $this->assertContains('tasks', $relationships);
        $this->assertContains('tasks.items', $relationships);
        $this->assertContains('friends', $relationships);
    }

    /**
     * Get query that contains connections.
     *
     * @return string
     */
    public function query()
    {
        return '{
            userQuery {
                name
                tasks(first: 15) {
                    edges {
                        node {
                            title
                            items(first: 10) {
                                edges {
                                    node {
                                        foo
                                        bar
                                        assigned(first: 5) {
                                            edges {
                                                node {
                                                    email
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                friends(first: 5) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        }';
    }
}
