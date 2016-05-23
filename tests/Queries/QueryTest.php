<?php

namespace Nuwave\Relay\Tests\Queries;

use GraphQL;
use Nuwave\Relay\Support\Definition\GraphQLQuery;
use Nuwave\Relay\Tests\Support\Models\User;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Relay\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Relay\Tests\Support\GraphQL\Queries\UserQuery;
use Nuwave\Relay\Tests\TestCase;

class QueryTest extends TestCase
{
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
                'name' => 'foo'
            ]
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->query('userQuery', UserQuery::class);

        $this->assertEquals(['data' => $expected], $this->executeQuery($query));
    }
}
