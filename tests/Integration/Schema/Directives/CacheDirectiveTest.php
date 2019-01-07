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
    private $cache;

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
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            id: ID!
            name: String @cache
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar'
                ]
            ]
        ]);

        $this->assertSame('foobar', $this->cache->get('user:1:name'));
    }

    /**
     * @test
     */
    public function itCanPlaceCacheKeyOnAnyField()
    {
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            id: ID!
            name: String @cache
            email: String @cacheKey
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar'
                ]
            ]
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

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            id: ID!
            name: String @cache(private: true)
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";

        $this->query('
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar'
                ]
            ]
        ]);

        $this->assertSame('foobar', $this->cache->get($cacheKey));
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

        $this->query('
        {
            users(count: 5) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $result = $this->cache->get('query:users:count:5');

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
            user(id: ' . $user->getKey() . ') {
                id
                name
                posts(count: 3) {
                    data {
                        title
                    }
                }
            }
        }
        ';

        $firstResponse = $this->query($query);

        $posts = $this->cache->get("user:{$user->getKey()}:posts:count:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            // TODO: Find a better way of doing this
            if (! Str::contains($query->sql, [
                'drop',
                'delete',
                'migrations',
                'aggregate',
                'limit 1',
            ])) {
                $queries++;
            }
        });

        $cachedResponse = $this->query($query);

        // Get the the original user and the `find` directive checks the count
        $this->assertSame(0, $queries);
        $this->assertSame(
            $firstResponse->json(),
            $cachedResponse->json()
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
                posts(count: 3) {
                    data {
                        title
                    }
                }
            }
        }
        ';

        $firstResponse = $this->query($query);

        $posts = $this->cache->tags($tags)->get("user:{$user->getKey()}:posts:count:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            // TODO: Find a better way of doing this
            if (! Str::contains($query->sql, [
                'drop',
                'delete',
                'migrations',
                'aggregate',
                'limit 1',
            ])) {
                $queries++;
            }
        });

        $cachedResponse = $this->query($query);

        // Get the the original user and the `find` directive checks the count
        $this->assertSame(0, $queries);
        $this->assertSame(
            $firstResponse->json(),
            $cachedResponse->json()
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
