<?php


namespace Tests\Integration\Directives\Fields;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\TestCase;
use Tests\Utils\Models\User;

class PaginateDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    public function testCanCreateQueryPaginators()
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

        graphql()->build($schema);
        $result = graphql()->execute($query);
        //dd($result);
        $this->assertEquals(5, array_get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, array_get($result->data, 'users.paginatorInfo.total'));
        $this->assertEquals(1, array_get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertCount(5, array_get($result->data, 'users.data'));
    }
}
