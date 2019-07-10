<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Product;

class BelongsToTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * User's team.
     *
     * @var \Tests\Utils\Models\Team
     */
    protected $team;

    /**
     * User's company.
     *
     * @var \Tests\Utils\Models\Company
     */
    protected $company;

    protected function setUp(): void
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
    public function itCanResolveBelongsToRelationship(): void
    {
        $this->be($this->user);

        $this->schema = '
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

        $this->graphQL('
        {
            user {
                company {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'company' => [
                        'name' => $this->company->name,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToWithCustomName(): void
    {
        $this->be($this->user);

        $this->schema = '
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

        $this->graphQL('
        {
            user {
                account {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'account' => [
                        'name' => $this->company->name,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToRelationshipWithTwoRelation(): void
    {
        $this->be($this->user);

        $this->schema = '
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

        $this->graphQL('
        {
            user {
                company {
                    name
                }
                team {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'company' => [
                        'name' => $this->company->name,
                    ],
                    'team' => [
                        'name' => $this->team->name,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBelongsToRelationshipWhenMainModelHasCompositePrimaryKey(): void
    {
        $this->be($this->user);

        $products = factory(Product::class, 2)->create();

        $this->schema = '
        type Color {
            id: ID!
            name: String
        }
                
        type Product {
            barcode: String!
            uuid: String!
            name: String!
            color: Color @belongsTo

        }
            
        type Query {
            products: [Product] @paginate
        }
        ';

        $this->graphQL('
        {
            products(first: 2) {     
                data{
                    barcode
                    uuid
                    name
                    color {
                        id
                        name
                    }
                }                           
            }
        }
        ')->assertJson([
            'data' => [
                'products' => [
                    'data' => [
                        [
                            'color' => [
                                'id' => $products[0]->color_id,
                            ],
                        ],
                        [
                            'color' => [
                                'id' => $products[1]->color_id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
