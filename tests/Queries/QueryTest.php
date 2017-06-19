<?php

namespace Nuwave\Lighthouse\Tests\Queries;

use GraphQL;
use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\PostType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Queries\UserQuery;

class QueryTest extends TestCase
{
    use GlobalIdTrait;

    /**
     * @test
     */
    public function itCanExecuteQuery()
    {
        $query = '{
            userQuery {
                name
            }
        }';

        $expected = [
            'userQuery' => [
                'name' => 'foo',
            ],
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);

        $this->assertEquals(['data' => $expected], $this->executeQuery($query));
    }

    /**
     * @test
     */
    public function itCanExecuteConnectionQuery()
    {
        $query = '{
            userQuery {
                name
                tasks(order: "DESC") {
                    edges {
                        node {
                            title
                        }
                    }
                }
            }
        }';

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);

        $data = $this->executeQuery($query)['data'];
        $this->assertSame('foo', array_get($data, 'userQuery.name'));
        $this->assertCount(5, array_get($data, 'userQuery.tasks.edges', []));
        $this->assertSame('foo', array_first(array_pluck(array_get($data, 'userQuery.tasks.edges', []), 'node.title')));
    }

    /**
     * @test
     */
    public function itCanResolveNodeInterface()
    {
        $id = $this->encodeGlobalId(UserType::class, 1);

        $query = '{
            node(id:"'.$id.'") {
                id
                ... on User {
                    email
                }
            }
        }';

        $expected = [
            'node' => [
                'id' => $id,
                'email' => 'foo@bar.com',
            ],
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);

        $data = $this->executeQuery($query)['data'];
        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function itCanResolveNodeWithoutRegularIdAttribute()
    {
        $id = $this->encodeGlobalId(PostType::class, 1);

        $query = '{
            node(id:"'.$id.'") {
                id
                ... on Post {
                    title
                }
            }
        }';

        $expected = [
            'node' => [
                'id' => $id,
                'title' => 'Foobar',
            ],
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('post', PostType::class);

        $data = $this->executeQuery($query)['data'];
        $this->assertEquals($expected, $data);
    }
}
