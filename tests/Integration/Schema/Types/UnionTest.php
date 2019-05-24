<?php

namespace Tests\Integration\Schema\Types;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Collection;

class UnionTest extends DBTestCase
{
    /**
     * @test
     * @dataProvider withAndWithoutCustomTypeResolver
     * @param  string  $schema
     * @param  string  $query
     * @return void
     */
    public function itCanResolveUnionTypes(string $schema, string $query): void
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function resolve(): Collection
    {
        $users = User::all();
        $posts = Post::all();

        return $users->concat($posts);
    }

    /**
     * @return array[]
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
     * @param  bool  $withCustomTypeResolver
     * @return string[] [string $schema, string $query]
     */
    public function schemaAndQuery(bool $withCustomTypeResolver): array
    {
        $prefix = $withCustomTypeResolver
            ? 'Custom'
            : '';

        $customResolver = $withCustomTypeResolver
            ? '@union(resolveType: "Tests\\\\Utils\\\\Unions\\\\CustomStuff@resolveType")'
            : '';

        return [
            "
            union Stuff {$customResolver} = {$prefix}User | {$prefix}Post
            
            type {$prefix}User {
                name: String!
            }
            
            type {$prefix}Post {
                title: String!
            }
            
            type Query {
                stuff: [Stuff!]! @field(resolver: \"{$this->qualifyTestResolver()}\")
            }
            ",
            "
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
