<?php

namespace Tests\Integration\Schema\Types;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnionTest extends DBTestCase
{
    use RefreshDatabase;
    
    /**
     * @test
     * @dataProvider withAndWithoutCustomTypeResolver
     */
    public function itCanResolveUnionTypes(string $schema, string $query)
    {
        // This creates a user with it
        factory(Post::class)->create(
        // Prevent creating more users through nested factory
            ['task_id' => 1]
        );
        
        $result = $this->execute($schema, $query);
        
        $this->assertCount(2, array_get($result, 'data.stuff'));
        $this->assertArrayHasKey('name', array_get($result, 'data.stuff.0'));
        $this->assertArrayHasKey('title', array_get($result, 'data.stuff.1'));
    }
    
    public function fetchResults(): Collection
    {
        $users = User::all();
        $posts = Post::all();
        
        return $users->concat($posts);
    }
    
    public function withAndWithoutCustomTypeResolver(): array
    {
        return [
            // This uses the default type resolver
            $this->schema(''),
            // This scenario requires a custom resolver, since the types User and Post do not match
            $this->schema('Custom'),
        ];
    }
    
    public function schema(string $prefix): array
    {
        $fieldResolver = addslashes(self::class). '@fetchResults';

        return [
            "
            union {$prefix}Stuff = {$prefix}User | {$prefix}Post
            
            type {$prefix}User {
                name: String!
            }
            
            type {$prefix}Post {
                title: String!
            }
            
            type Query {
                stuff: [{$prefix}Stuff!]! @field(resolver: \"$fieldResolver\")
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
