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

    public function testStoreResolverResultInCache(): void
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

    public function testPlaceCacheKeyOnAnyField(): void
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

    public function testStoreResolverResultInPrivateCache(): void
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

    public function testStoreResolverResultInCacheWhenUsingNodeDirective(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type User @node {
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

    public function testStorePaginateResolverInCache(): void
    {
        factory(User::class, 5)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User] @paginate(type: PAGINATOR, model: "User") @cache
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

    public function testCacheHasManyResolver(): void
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
            posts: [Post] @hasMany(type: PAGINATOR) @cache
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

    public function testAttachTagsToCache(): void
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
            posts: [Post] @hasMany(type: PAGINATOR) @cache
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

    public function testUseFalsyResultsInCache(): void
    {
        $this->mockResolver([
            'id' => 1,
            'field_boolean' => true,
            'field_string' => 'value',
            'field_integer' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            field_boolean: Boolean @cache
            field_string: String @cache
            field_integer: Int @cache
        }

        type Query {
            user: User @mock
        }
        ';

        // TTL is required for laravel 5.7 and prior
        // @see https://laravel.com/docs/5.8/upgrade#psr-16-conformity
        $this->cache->setMultiple([
            'user:1:field_boolean' => false,
            'user:1:field_string' => '',
            'user:1:field_integer' => 0,
        ], 1);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                field_boolean
                field_string
                field_integer
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'field_boolean' => false,
                    'field_string' => '',
                    'field_integer' => 0,
                ],
            ],
        ]);
    }
}
