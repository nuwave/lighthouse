<?php

namespace Tests\Integration\Schema\Types;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class UnionTest extends DBTestCase
{
    /**
     * @dataProvider withAndWithoutCustomTypeResolver
     */
    public function testResolveUnionTypes(string $schema, string $query): void
    {
        // This creates a user with it
        factory(Post::class)->create(
            // Prevent creating more users through nested factory
            ['task_id' => 1]
        );

        $this->schema = $schema;

        $this->graphQL($query)->assertJsonStructure([
            'data' => [
                'stuff' => [
                    [
                        'name',
                    ],
                    [
                        'title',
                    ],
                ],
            ],
        ]);
    }

    public function testConsidersRenamedModels(): void
    {
        // This creates a user with it
        factory(Post::class)->create(
            // Prevent creating more users through nested factory
            ['task_id' => 1]
        );

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Stuff = Foo | Post

        type Foo @model(class: "User") {
            name: String!
        }

        type Post {
            title: String!
        }

        type Query {
            stuff: [Stuff!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            stuff {
                ... on Foo {
                    name
                }
                ... on Post {
                    title
                }
            }
        }
        ')->assertJsonStructure([
            'data' => [
                'stuff' => [
                    [
                        'name',
                    ],
                    [
                        'title',
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsOnAmbiguousSchemaMapping(): void
    {
        // This creates a user with it
        factory(Post::class)->create(
            // Prevent creating more users through nested factory
            ['task_id' => 1]
        );

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Nameable = Foo | Post

        type Foo @model(class: "User") {
            name: String!
        }

        type Post @model(class: "User") {
            title: String!
        }

        type Query {
            stuff: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, ['Foo', 'Post'])
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            stuff {
                ... on Foo {
                    name
                }
                ... on Post {
                    title
                }
            }
        }
        ');
    }

    public function testThrowsOnNonOverlappingSchemaMapping(): void
    {
        // This creates a user with it
        factory(Post::class)->create(
            // Prevent creating more users through nested factory
            ['task_id' => 1]
        );

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Stuff = Post

        type Post {
            title: String!
        }

        type NotPartOfUnion @model(class: "User") {
            id: String!
        }

        type Query {
            stuff: [Stuff!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, [])
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            stuff {
                ... on Post {
                    title
                }
            }
        }
        ');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User|\Tests\Utils\Models\Post>
     */
    public function fetchResults(): EloquentCollection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User|\Tests\Utils\Models\Post> $results */
        $results = new EloquentCollection();

        return $results
            ->concat(User::all())
            ->concat(Post::all());
    }

    /**
     * @return array<int, array<string>>
     */
    public function withAndWithoutCustomTypeResolver(): array
    {
        return [
            // This uses the default type resolver
            $this->schemaAndQuery(false),
            // This scenario requires a custom resolver, since the types User and Post do not match
            $this->schemaAndQuery(true),
        ];
    }

    /**
     * @return array<string> [string $schema, string $query]
     */
    public function schemaAndQuery(bool $withCustomTypeResolver): array
    {
        $prefix = $withCustomTypeResolver
            ? 'Custom'
            : '';

        $customResolver = $withCustomTypeResolver
            ? /** @lang GraphQL */ '@union(resolveType: "Tests\\\\Utils\\\\Unions\\\\CustomStuff@resolveType")'
            : '';

        return [
/** @lang GraphQL */ "
            union Stuff {$customResolver} = {$prefix}User | {$prefix}Post

            type {$prefix}User {
                name: String!
            }

            type {$prefix}Post {
                title: String!
            }

            type Query {
                stuff: [Stuff!]! @field(resolver: \"{$this->qualifyTestResolver('fetchResults')}\")
            }
            ",
/** @lang GraphQL */ "
            {
                stuff {
                    ... on {$prefix}User {
                        name
                    }
                    ... on {$prefix}Post {
                        title
                    }
                }
            }
            ",
        ];
    }
}
