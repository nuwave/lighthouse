<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class PaginateDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanCreateQueryPaginators()
    {
        $users = factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        type Query {
            users: [User!]! @paginate(type: "paginator" model: "User")
        }
        ';

        $query = '{
            users(count: 5) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                    name
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertEquals(5, array_get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, array_get($result->data, 'users.paginatorInfo.total'));
        $this->assertEquals(1, array_get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertCount(5, array_get($result->data, 'users.data'));
    }

    /**
     * @test
     */
    public function itCanCreateQueryConnections()
    {
        $users = factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        type Query {
            users: [User!]! @paginate(type: "connection" model: "User")
        }
        ';

        $query = '{
            users(first: 5) {
                pageInfo {
                    hasNextPage
                }
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertTrue(array_get($result->data, 'users.pageInfo.hasNextPage'));
        $this->assertCount(5, array_get($result->data, 'users.edges'));
    }
}
