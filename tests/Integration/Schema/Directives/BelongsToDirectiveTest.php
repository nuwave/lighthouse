<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Product;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class BelongsToDirectiveTest extends DBTestCase
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

    public function setUp(): void
    {
        parent::setUp();

        $this->company = factory(Company::class)->create();
        $this->team = factory(Team::class)->create();
        $this->user = factory(User::class)->create([
            'company_id' => $this->company->getKey(),
            'team_id' => $this->team->getKey(),
        ]);
    }

    public function testCanResolveBelongsToRelationship(): void
    {
        $this->be($this->user);

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanResolveBelongsToWithCustomName(): void
    {
        $this->be($this->user);

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanResolveBelongsToRelationshipWithTwoRelation(): void
    {
        $this->be($this->user);

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanResolveBelongsToRelationshipWhenMainModelHasCompositePrimaryKey(): void
    {
        $this->be($this->user);

        $products = factory(Product::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testBelongsToItself(): void
    {
        /** @var \Tests\Utils\Models\Post $parent */
        $parent = factory(Post::class)->create();

        /** @var \Tests\Utils\Models\Post $child */
        $child = factory(Post::class)->make();
        $child->parent()->associate($parent);
        $child->save();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
            parent: Post @belongsTo
        }

        type Query {
            posts: [Post!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                posts {
                    id
                    parent {
                        id
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'posts' => [
                        [
                            'id' => $parent->id,
                            'parent' => null,
                        ],
                        [
                            'id' => $child->id,
                            'parent' => [
                                'id' => $parent->id,
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
