<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;

class AllDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanGetAllModelsAsRootField(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @all(model: "User")
        }
        ';

        $this->graphQL('
        {
            users {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itCanUseScopes(): void
    {
        $companyA = factory(Company::class)->create(['name' => 'CompanyA']);
        $userA = factory(User::class)->create(['name' => 'A', 'company_id' => $companyA->id]);
        $userB = factory(User::class)->create(['name' => 'B', 'company_id' => $companyA->id]);
        $userC = factory(User::class)->create(['name' => 'C', 'company_id' => $companyA->id]);

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users(company: String!): [User!]! @all(model: "User", scopes: ["companyName"])
        }
        ';

        $this->graphQL('
        {
            users(company: "CompanyA") {
                id
                name
            }
        }
        ')->assertJsonCount(3, 'data.users');
    }

    /**
     * @test
     */
    public function itCanGetAllAsNestedField(): void
    {
        factory(Post::class, 2)->create([
            // Do not create those, as they would create more users
            'task_id' => 1,
        ]);

        $this->schema = '
        type User {
            posts: [Post!]! @all
        }

        type Post {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users {
                posts {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    [
                        'posts' => [
                            [
                                'id' => '1',
                            ],
                            [
                                'id' => '2',
                            ],
                        ],
                    ],
                    [
                        'posts' => [
                            [
                                'id' => '1',
                            ],
                            [
                                'id' => '2',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanGetAllModelsFiltered(): void
    {
        $users = factory(User::class, 3)->create();
        $userName = $users->first()->name;

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users(name: String @neq): [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users(name: "'.$userName.'") {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }
}
