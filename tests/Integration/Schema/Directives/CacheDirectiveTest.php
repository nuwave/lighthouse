<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class CacheDirectiveTest extends DBTestCase
{
    /**
     * @var \Illuminate\Cache\TaggedCache
     */
    protected $cache;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->cache = $app->make('cache');
    }

    public function testCanStoreResolverResultInCache(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String @cache
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanPlaceCacheKeyOnAnyField(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
            'email' => 'foo@bar.com',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String @cache
            email: String @cacheKey
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanStoreResolverResultInPrivateCache(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $cacheKey = "auth:{$user->getKey()}:user:1:name";

        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String @cache(private: true)
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanStoreResolverResultInCacheWhenUseModelDirective(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type User @model {
            name: String @cache
            posts: [Post!]!
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testFallsBackToPublicCacheIfUserIsNotAuthenticated(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String @cache(private: true)
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanStorePaginateResolverInCache(): void
    {
        factory(User::class, 5)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User] @paginate(type: "paginator", model: "User") @cache
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testCanCacheHasManyResolver(): void
    {
        $user = factory(User::class)->create();

        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $this->schema = /** @lang GraphQL */ '
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

        $query = /** @lang GraphQL */ '
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
            if (Str::contains($query->sql, 'select * from `posts`')) {
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
            $firstResponse->json(),
            $cachedResponse->json()
        );
    }

    public function testCanAttachTagsToCache(): void
    {
        config(['lighthouse.cache.tags' => true]);

        $user = factory(User::class)->create();
        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $tags = ['graphql:user:1', 'graphql:user:1:posts'];

        $this->schema = /** @lang GraphQL */ '
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

        $query = /** @lang GraphQL */ '
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
            if (Str::contains($query->sql, 'select * from `posts`')) {
                $dbQueryCountForPost++;
            }
        });

        $firstResponse = $this->graphQL($query);

        $posts = $this->cache
            ->tags($tags)
            ->get("user:{$user->getKey()}:posts:first:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $cachedResponse = $this->graphQL($query);

        $this->assertSame(1, $dbQueryCountForPost, 'This query should only run once and be cached on the second run.');
        $this->assertSame(
            $firstResponse->json(),
            $cachedResponse->json()
        );
    }
}
