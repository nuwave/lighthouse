<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Product;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class BelongsToDirectiveTest extends DBTestCase
{
    public function testResolveBelongsToRelationship(): void
    {
        $company = factory(Company::class)->create();

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->be($user);

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
                        'name' => $company->name,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveBelongsToWithCustomName(): void
    {
        $company = factory(Company::class)->create();

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->be($user);

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
                        'name' => $company->name,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveBelongsToRelationshipWithTwoRelation(): void
    {
        $company = factory(Company::class)->create();
        $team = factory(Team::class)->create();

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->team()->associate($team);
        $user->save();

        $this->be($user);

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
                        'name' => $company->name,
                    ],
                    'team' => [
                        'name' => $team->name,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveBelongsToRelationshipWhenMainModelHasCompositePrimaryKey(): void
    {
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
        /** @var Post $parent */
        $parent = factory(Post::class)->create();

        /** @var Post $child */
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

    public function testDoesNotShortcutForeignKeySelectionByDefault(): void
    {
        $company = factory(Company::class)->create();

        /** @var User $user */
        $user = factory(User::class)->make();
        $user->company()->associate($company);
        $user->save();

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID! @rename(attribute: "uuid")
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
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'company' => [
                        'id' => $company->uuid,
                    ],
                ],
            ],
        ]);
    }

    public function testShortcutsForeignKeySelectID(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $user->setRelations([]);

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID!
        }

        type User {
            company: Company @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->assertNoQueriesExecuted(function () use ($company): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                user {
                    company {
                        id
                    }
                }
            }
            ')->assertJson([
                'data' => [
                    'user' => [
                        'company' => [
                            'id' => $company->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $user->setRelations([]);

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID!
        }

        type User {
            company: Company @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->assertNoQueriesExecuted(function (): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                user {
                    company {
                        __typename
                    }
                }
            }
            ')->assertJson([
                'data' => [
                    'user' => [
                        'company' => [
                            '__typename' => 'Company',
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectIDAndTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $user->setRelations([]);

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID!
        }

        type User {
            company: Company @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->assertNoQueriesExecuted(function () use ($company): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                user {
                    company {
                        __typename
                        id
                    }
                }
            }
            ')->assertJson([
                'data' => [
                    'user' => [
                        'company' => [
                            '__typename' => 'Company',
                            'id' => $company->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testDoesNotShortcutForeignKeyIfQueryHasFieldSelection(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type User {
            company: Company @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->assertQueryCountMatches(1, function () use ($company): void {
            $this->graphQL(/** @lang GraphQL */ '
            {
                user {
                    company {
                        id
                        name
                    }
                }
            }
            ')->assertJson([
                'data' => [
                    'user' => [
                        'company' => [
                            'id' => $company->id,
                            'name' => $company->name,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testDoesNotShortcutForeignKeyIfQueryHasConditions(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type Company {
            id: ID!
        }

        type User {
            company(name: String @eq): Company @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->assertQueryCountMatches(1, function () use ($company): void {
            $this->graphQL(/** @lang GraphQL */ '
            query ($name: String) {
                user {
                    company(name: $name) {
                        id
                    }
                }
            }
            ', [
                'name' => "{$company->name} no match",
            ])->assertJson([
                'data' => [
                    'user' => [
                        'company' => null,
                    ],
                ],
            ]);
        });
    }
}
