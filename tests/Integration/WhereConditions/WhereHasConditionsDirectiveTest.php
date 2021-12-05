<?php

namespace Tests\Integration\WhereConditions;

use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Category;
use Tests\Utils\Models\Location;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

class WhereHasConditionsDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type User {
        id: ID!
        name: String
        email: String
        roles: [Role!]! @belongsToMany
    }

    type Post {
        id: ID!
        title: String
        body: String
        categories: [Category!] @belongsToMany
    }

    type Category {
        category_id: ID!
        name: String
        parent: Category @belongsTo
    }

    type Company {
        id: ID!
        name: String
    }

    type Role {
        id: Int!
        name: String!
    }

    type Location {
        id: ID!
        parent: Location @belongsTo
        children: [Location!] @hasMany
    }

    type Query {
        posts(
            hasUser: _ @whereHasConditions(relation: "user")
            hasCategories: _ @whereHasConditions(relation: "categories")
        ): [Post!]! @all

        users(
            hasCompany: _ @whereHasConditions(relation: "company")
            hasPost: _ @whereHasConditions(relation: "posts")
            hasRoles: _ @whereHasConditions(relation: "roles")
        ): [User!]! @all

        companies(
            hasUser: _ @whereHasConditions(relation: "users")
        ): [Company!]! @all

        whitelistedColumns(
            hasCompany: _ @whereHasConditions(relation: "company", columns: ["id", "camelCase"])
        ): [User!]! @all

        withoutRelation(
            hasCompany: _ @whereHasConditions
        ): [User!]! @all

        locations(
            hasParent: _ @whereHasConditions(columns: ["id"]),
            hasChildren: _ @whereHasConditions(columns: ["id"])
        ): [Location!]! @all
    }
    ';

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [WhereConditionsServiceProvider::class]
        );
    }

    public function testExistenceWithEmptyCondition(): void
    {
        factory(User::class)->create([
            'company_id' => null,
        ]);

        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {}
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testIgnoreNullCondition(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: null
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testWithoutRelationName(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            withoutRelation(
                hasCompany: {
                    column: "id"
                    value: 1
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'withoutRelation' => [
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testOperatorOr(): void
    {
        factory(User::class, 5)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                hasCompany: {
                    OR: [
                        {
                            column: "id"
                            value: 1
                        }
                        {
                            column: "id"
                            value: 3
                        }
                    ]
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => '1',
                    ],
                    [
                        'id' => '3',
                    ],
                ],
            ],
        ]);
    }

    public function testWhereHasBelongsToMany(): void
    {
        factory(User::class)->create();

        /** @var Role $role */
        $role = factory(Role::class)->create();

        /** @var User $user */
        $user = factory(User::class)->create();

        $user->roles()->attach($role);

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: Mixed!) {
            users(
                hasRoles: {
                    column: "id",
                    value: $id
                }
            ) {
                id
            }
        }
        ', [
            'id' => $role->getKey(),
        ])->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => (string) $user->getKey(),
                    ],
                ],
            ],
        ]);
    }

    public function testWhereHasBelongsToManyOrNestedConditions(): void
    {
        $parentWithoutPosts = factory(Category::class)->create();

        $categoryWithParentWithoutPosts = factory(Category::class)->create();
        $categoryWithParentWithoutPosts->parent()->associate($parentWithoutPosts);
        $categoryWithParentWithoutPosts->save();

        $postWithCategoryWithParentWithoutPosts = factory(Post::class)->create();
        $postWithCategoryWithParentWithoutPosts->categories()->attach($categoryWithParentWithoutPosts);

        $parentWithPosts = factory(Category::class)->create();
        $postWithParent = factory(Post::class)->create();
        $postWithParent->categories()->attach($parentWithoutPosts);

        $categoryWithParentWithPosts = factory(Category::class)->create();
        $categoryWithParentWithPosts->parent()->associate($parentWithPosts);
        $categoryWithParentWithPosts->save();

        $postWithCategoryWithParentWithPosts = factory(Post::class)->create();
        $postWithCategoryWithParentWithPosts->categories()->attach($categoryWithParentWithPosts);

        $parentWithFooPosts = factory(Category::class)->create();
        /** @var Post $fooPost */
        $fooPost = factory(Post::class)->make();
        $fooPost->title = 'foo';
        $fooPost->save();
        $fooPost->categories()->attach($parentWithFooPosts);

        $categoryWithParentWithFooPosts = factory(Category::class)->create();
        $categoryWithParentWithFooPosts->parent()->associate($parentWithFooPosts);
        $categoryWithParentWithFooPosts->save();

        $postWithCategoryWithParentWithFooPosts = factory(Post::class)->create();
        $postWithCategoryWithParentWithFooPosts->categories()->attach($categoryWithParentWithFooPosts);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(
                hasCategories: {
                    HAS: {
                        relation: "parent.posts"
                        condition: {
                            column: "title"
                            value: "foo"
                        }
                    }
                }
            ) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'posts' => [
                    [
                        'id' => (string) $postWithCategoryWithParentWithFooPosts->getKey(),
                    ],
                ],
            ],
        ]);
    }

    public function testWhereHasNestedRelationWithDotNotation(): void
    {
        $category1 = factory(Category::class)->create();

        $category2 = factory(Category::class)->create();
        $category2->parent()->associate($category1);
        $category2->save();

        $category3 = factory(Category::class)->create();
        $category3->parent()->associate($category2);
        $category3->save();

        $category4 = factory(Category::class)->create();
        $category4->parent()->associate($category3);
        $category4->save();

        $category5 = factory(Category::class)->create();
        $category5->parent()->associate($category4);
        $category5->save();

        factory(Post::class)->create();

        $post2 = factory(Post::class)->create();
        $post2->categories()->attach($category2);

        $post3 = factory(Post::class)->create();
        $post3->categories()->attach($category3);

        $post4 = factory(Post::class)->create();
        $post4->categories()->attach($category4);

        $post5 = factory(Post::class)->create();
        $post5->categories()->attach($category5);

        $this->graphQL(/** @lang GraphQL */ '
        query ($categoryId: Mixed!) {
            posts(
                hasCategories: {
                    OR: [
                        {
                            column: "categories.category_id",
                            value: $categoryId
                        },
                        {
                            HAS: {
                                relation: "parent",
                                condition: {
                                    OR: [
                                        {
                                            column: "category_id",
                                            value: $categoryId
                                        },
                                        {
                                            HAS: {
                                                relation: "parent",
                                                condition: {
                                                    column: "category_id",
                                                    value: $categoryId
                                                }
                                            }
                                        }
                                    ]
                                }
                            }
                        },
                    ]
                }
            ) {
                id
            }
        }
        ', [
            'categoryId' => $category3->getKey(),
        ])->assertExactJson([
            'data' => [
                'posts' => [
                    [
                        'id' => (string) $post3->getKey(),
                    ],
                    [
                        'id' => (string) $post4->getKey(),
                    ],
                    [
                        'id' => (string) $post5->getKey(),
                    ],
                ],
            ],
        ]);
    }

    public function testWhereConditionsHasNestedTables(): void
    {
        $category1 = factory(Category::class)->create();

        $category2 = factory(Category::class)->create();
        $category2->parent()->associate($category1);
        $category2->save();

        $category3 = factory(Category::class)->create();
        $category3->parent()->associate($category2);
        $category3->save();

        $category4 = factory(Category::class)->create();
        $category4->parent()->associate($category3);
        $category4->save();

        $post = factory(Post::class)->create();
        $post->categories()->attach($category4);

        $this->graphQL(/** @lang GraphQL */ '
        query ($hasCategories: WhereConditions) {
            posts(hasCategories: $hasCategories) {
                id
                title
                body
                categories {
                    category_id
                    name
                    parent {
                        category_id
                        name
                        parent {
                            category_id
                            name
                            parent {
                                category_id
                                name
                            }
                        }
                    }
                }
            }
        }
        ', [
            'hasCategories' => [
                'HAS' => [
                    'relation' => 'parent.parent.parent',
                    'condition' => [
                        'column' => 'category_id',
                        'value' => $category1->category_id,
                    ],
                ],
            ],
        ])->assertExactJson([
            'data' => [
                'posts' => [
                    [
                        'id' => (string) $post->id,
                        'title' => $post->title,
                        'body' => $post->body,
                        'categories' => [
                            [
                                'category_id' => (string) $category4->category_id,
                                'name' => $category4->name,
                                'parent' => [
                                    'category_id' => (string) $category3->category_id,
                                    'name' => $category3->name,
                                    'parent' => [
                                        'category_id' => (string) $category2->category_id,
                                        'name' => $category2->name,
                                        'parent' => [
                                            'category_id' => (string) $category1->category_id,
                                            'name' => $category1->name,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testWhereHasBelongsToSameTableRelationship(): void
    {
        /** @var \Tests\Utils\Models\Location $parent */
        $parent = factory(Location::class)->create();

        /** @var \Tests\Utils\Models\Location $child */
        $child = factory(Location::class)->make();
        $child->parent()->associate($parent);
        $child->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($hasParent: QueryLocationsHasParentWhereHasConditions) {
            locations(hasParent: $hasParent) {
                id
                parent {
                    id
                }
                children {
                    id
                }
            }
        }
        ', [
            'hasParent' => [
                'column' => 'ID',
                'value' => $parent->id,
            ],
        ])->assertExactJson([
            'data' => [
                'locations' => [
                    [
                        'id' => (string) $child->id,
                        'parent' => [
                            'id' => (string) $parent->id,
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testWhereHasHasManySameTableRelationship(): void
    {
        /** @var \Tests\Utils\Models\Location $parent */
        $parent = factory(Location::class)->create();

        /** @var \Tests\Utils\Models\Location $child */
        $child = factory(Location::class)->make();
        $child->parent()->associate($parent);
        $child->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($hasChildren: QueryLocationsHasChildrenWhereHasConditions) {
            locations(hasChildren: $hasChildren) {
                id
                parent {
                    id
                }
                children {
                    id
                }
            }
        }
        ', [
            'hasChildren' => [
                'column' => 'ID',
                'value' => $child->id,
            ],
        ])->assertExactJson([
            'data' => [
                'locations' => [
                    [
                        'id' => (string) $parent->id,
                        'parent' => null,
                        'children' => [
                            [
                                'id' => (string) $child->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
