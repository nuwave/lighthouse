<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnionDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanResolveUnionTypes()
    {
        // This creates a user with it
        factory(Post::class)->create(
            // Prevent creating more users through nested factory
            ['task_id' => 1]
        );

        $schema = '
        union Stuff @union(resolver: "' . addslashes(self::class) . '@resolveType") =
            User
            | Post
        
        type User {
            name: String!
        }
        
        type Post {
            title: String!
        }
        
        type Query {
            stuff: [Stuff!]! @field(resolver: "' . addslashes(self::class) . '@fetchResults")
        }
        ';
        $query = '
        {
            stuff {
                ... on User {
                    name
                }
                ... on Post {
                    title
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertCount(2, array_get($result, 'data.stuff'));
        $this->assertArrayHasKey('name', array_get($result, 'data.stuff.0'));
        $this->assertArrayHasKey('title', array_get($result, 'data.stuff.1'));
    }

    public function resolveType($value): \GraphQL\Type\Definition\ObjectType
    {
        /** @var TypeRegistry $typeRegistry */
        $typeRegistry = resolve(TypeRegistry::class);
        if ($value instanceof User) {
            return $typeRegistry->get('User');
        } elseif($value instanceof Post){
            return $typeRegistry->get('Post');
        }
    }

    public function fetchResults(): Collection
    {
        $users = User::all();
        $posts = Post::all();

        return $users->concat($posts);
    }
}
