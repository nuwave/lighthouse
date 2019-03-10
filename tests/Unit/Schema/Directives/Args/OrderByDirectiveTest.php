<?php


namespace Tests\Unit\Schema\Directives\Args;


use Tests\DBTestCase;
use Tests\Utils\Models\User;

/**
 * Class OrderByDirectiveTest
 *
 * @package Tests\Unit\Schema\Directives\Args
 */
class OrderByDirectiveTest extends DBTestCase
{

    /**
     * @test
     */
    public function itCanOrderByTheGivenFieldAndSortOrderASC()
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->schema = "
            type User {
                name: String
                team_id: Int
            }
            
            type Query {
                users(orderBy: [OrderByClause!]! @orderBy): [User!]! @all
            }
        ";

        $result = $this->query("
            {
                users(orderBy: [{field:\"name\", order:ASC }]){
                    name
                }
            }
        ")->jsonGet('data.users.*.name');

        $this->assertEquals('A', $result[0]);
        $this->assertEquals('B', $result[1]);
    }

    /**
     * @test
     */
    public function itCanOrderByTheGivenFieldAndSortOrderDESC()
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->schema = "
            type User {
                name: String
                team_id: Int
            }
            
            type Query {
                users(orderBy: [OrderByClause!]! @orderBy): [User!]! @all
            }
        ";

        $result = $this->query("
            {
                users(orderBy: [{field:\"name\", order:DESC }]){
                    name
                }
            }
        ")->jsonGet('data.users.*.name');

        $this->assertEquals('B', $result[0]);
        $this->assertEquals('A', $result[1]);
    }

    /**
     * @test
     */
    public function itCanOrderByMultipleFields()
    {
        factory(User::class)->create(['name' => 'B', 'team_id' => 2]);
        factory(User::class)->create(['name' => 'A', 'team_id' => 5]);
        factory(User::class)->create(['name' => 'C', 'team_id' => 2]);

        $this->schema = "
            type User {
                name: String
                team_id: Int
            }
            
            type Query {
                users(orderBy: [OrderByClause!]! @orderBy): [User!]! @all
            }
        ";

        $result = $this->query("
            {
                users(orderBy: [{field:\"team_id\", order:ASC}, {field:\"name\", order:ASC }]){
                    name
                    team_id
                }
            }
        ")->jsonGet('data.users.*');

        $this->assertEquals($result[0]['team_id'], 2);
        $this->assertEquals($result[0]['name'], 'B');

        $this->assertEquals($result[1]['team_id'], 2);
        $this->assertEquals($result[1]['name'], 'C');

        $this->assertEquals($result[2]['team_id'], 5);
        $this->assertEquals($result[2]['name'], 'A');
    }
}
