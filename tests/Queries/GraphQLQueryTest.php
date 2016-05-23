<?php

namespace Nuwave\Relay\Tests\Queries;

use GraphQL;
use Nuwave\Relay\Support\Definition\GraphQLQuery;
use Nuwave\Relay\Tests\Support\Models\User;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Relay\Tests\Support\GraphQL\Queries\UserQuery;
use Nuwave\Relay\Tests\TestCase;

class GraphQLQueryTest extends TestCase
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
        $graphql->addType(new UserType, 'user');
        $graphql->addQuery(new UserQuery, 'userQuery');

        $this->assertEquals(['data' => $expected], $this->executeQuery($query));
    }
}
