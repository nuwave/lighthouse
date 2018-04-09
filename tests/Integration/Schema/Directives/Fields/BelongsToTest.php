<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * Auth user.
     *
     * @var User
     */
    protected $user;

    /**
     * User's team.
     *
     * @var Team
     */
    protected $team;

    /**
     * User's company.
     *
     * @var Company
     */
    protected $company;

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->company = factory(Company::class)->create();
        $this->team = factory(Team::class)->create();
        $this->user = factory(User::class)->create([
            'company_id' => $this->company->getKey(),
            'team_id' => $this->team->getKey(),
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToRelationship()
    {
        $schema = '
        type Company {
            name: String!
        }
        type User {
            company: Company @belongsTo
        }
        type Query {
            user: User @auth
        }
        ';

        $this->be($this->user);

        $result = $this->execute($schema, '{ user { company { name } } }');
        $this->assertEquals($this->company->name, array_get($result->data, 'user.company.name'));
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToWithCustomName()
    {
        $schema = '
        type Company {
            name: String!
        }
        type User {
            account: Company @belongsTo(relation: "company")
        }
        type Query {
            user: User @auth
        }
        ';

        $this->be($this->user);
        $result = $this->execute($schema, '{ user { account { name } } }');
        $this->assertEquals($this->company->name, array_get($result->data, 'user.account.name'));
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToRelationshipWithTwoRelation()
    {
        $schema = '
        type Company {
            name: String!
        }
        type Team {
            name: String!
        }
        type User {
            company: Company @belongsTo
            team: Team @belongsTo
        }
        type Query {
            user: User @auth
        }
        ';

        $this->be($this->user);

        $result = $this->execute($schema, '{ user { company { name } team { name } } }');
        $this->assertEquals($this->company->name, array_get($result->data, 'user.company.name'));
        $this->assertEquals($this->team->name, array_get($result->data, 'user.team.name'));
    }
}
