<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Illuminate\Support\Str;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;

class CacheDirectiveTest extends DBTestCase
{
    /**
     * @var \Illuminate\Cache\CacheManager|\Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->cache = $app->make('cache');
    }

    /**
     * @test
     */
    public function itCanStoreResolverResultInCache(): void
    {
        $this->schema = "
        type User {
            id: ID!
            name: String @cache
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar',
                ],
            ],
        ]);

        $this->assertSame('foobar', $this->cache->get('user:1:name'));
    }

    /**
     * @test
     */
    public function itCanPlaceCacheKeyOnAnyField(): void
    {
        $this->schema = "
        type User {
            id: ID!
            name: String @cache
            email: String @cacheKey
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar',
                ],
            ],
        ]);

        $this->assertSame('foobar', $this->cache->get('user:foo@bar.com:name'));
    }

    /**
     * @test
     */
    public function itCanStoreResolverResultInPrivateCache(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $cacheKey = "auth:{$user->getKey()}:user:1:name";

        $this->schema = "
        type User {
            id: ID!
            name: String @cache(private: true)
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar',
                ],
            ],
        ]);

        $this->assertSame('foobar', $this->cache->get($cacheKey));
    }

    /**
     * @test
     */
    public function itFallsBackToPublicCacheIfUserIsNotAuthenticated(): void
    {
        $this->schema = "
        type User {
            id: ID!
            name: String @cache(private: true)
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar',
                ],
            ],
        ]);

        $this->assertSame('foobar', $this->cache->get('user:1:name'));
    }

    /**
     * @test
     */
    public function itCanStorePaginateResolverInCache(): void
    {
        factory(User::class, 5)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User] @paginate(type: "paginator", model: "User") @cache
        }
        ';

        $this->graphQL('
        {
            users(first: 5) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $result = $this->cache->get('query:users:first:5');

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(5, $result);
    }

    /**
     * @test
     */
    public function itCanCacheHasManyResolver(): void
    {
        $user = factory(User::class)->create();

        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $this->schema = '
        type Post {
            id: ID!
            title: String
        }
        
        type User {
            id: ID!
            name: String!
            posts: [Post] @hasMany(type: "paginator") @cache
        }
        
        type Query {
            user(id: ID! @eq): User @find(model: "User")
        }
        ';

        $query = '
        {
            user(id: '.$user->getKey().') {
                id
                name
                posts(first: 3) {
                    data {
                        title
                    }
                }
            }
        }
        ';

        $dbQueryCountForPost = 0;
        DB::listen(function (QueryExecuted $query) use (&$dbQueryCountForPost): void {
            if (Str::contains($query->sql, 'select * from "posts"')) {
                $dbQueryCountForPost++;
            }
        });

        $firstResponse = $this->graphQL($query);

        $posts = $this->cache->get("user:{$user->getKey()}:posts:first:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $cachedResponse = $this->graphQL($query);

        $this->assertSame(1, $dbQueryCountForPost, 'This query should only run once and be cached on the second run.');
        $this->assertSame(
            $firstResponse->jsonGet(),
            $cachedResponse->jsonGet()
        );
    }

    /**
     * @test
     */
    public function itCanAttachTagsToCache(): void
    {
        config(['lighthouse.cache.tags' => true]);

        $user = factory(User::class)->create();
        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $tags = ['graphql:user:1', 'graphql:user:1:posts'];

        $this->schema = '
        type Post {
            id: ID!
            title: String
        }
        
        type User {
            id: ID!
            name: String!
            posts: [Post] @hasMany(type: "paginator") @cache
        }
        
        type Query {
            user(id: ID! @eq): User @find(model: "User")
        }
        ';

        $query = '
        {
            user(id: '.$user->getKey().') {
                id
                name
                posts(first: 3) {
                    data {
                        title
                    }
                }
            }
        }
        ';

        $dbQueryCountForPost = 0;
        DB::listen(function (QueryExecuted $query) use (&$dbQueryCountForPost): void {
            if (Str::contains($query->sql, 'select * from "posts"')) {
                $dbQueryCountForPost++;
            }
        });

        $firstResponse = $this->graphQL($query);

        $posts = $this->cache->tags($tags)->get("user:{$user->getKey()}:posts:first:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $cachedResponse = $this->graphQL($query);

        $this->assertSame(1, $dbQueryCountForPost, 'This query should only run once and be cached on the second run.');
        $this->assertSame(
            $firstResponse->jsonGet(),
            $cachedResponse->jsonGet()
        );
    }

    /**
     * @return mixed[]
     */
    public function resolve(): array
    {
        return [
            'id' => 1,
            'name' => 'foobar',
            'email' => 'foo@bar.com',
        ];
    }
}
