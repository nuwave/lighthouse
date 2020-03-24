<?php


namespace Tests\Unit\Database;


use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class ColumnSelectTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */
        '
    type Query {
        usersWithTeam(team: ID! @scope(name: "withTeam")): [User!] @all
    }
    type User {
        id: ID!
        team: Team @belongsTo
    }
    type Team {
        id: ID!
    }
    ';

    public function testSelectsSameColumn(): void
    {
        $team = factory(Team::class)->create();
        factory(User::class, 2)->create(['team_id' => $team->id]);


        $this->graphQL(/** @lang GraphQL */ "
        query {
            usersWithTeam(team: 1) {
                id
            }
        }
        ")->assertJson([
            'data' => [
                'usersWithTeam' => [
                    [
                        'id' => '1',
                    ],
                    [
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }
}
