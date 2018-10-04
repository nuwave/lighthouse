<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;

class CacheDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanStoreResolverResultInCache()
    {
        $resolver = addslashes(self::class) . '@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";
        $query = '
        {
            user {
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foobar', array_get($result, 'data.user.name'));
        $this->assertEquals('foobar', resolve('cache')->get('user:1:name'));
    }

    /**
     * @test
     */
    public function itCanPlaceCacheKeyOnAnyField()
    {
        $resolver = addslashes(self::class) . '@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
            email: String @cacheKey
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";
        $query = '
        {
            user {
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foobar', array_get($result, 'data.user.name'));
        $this->assertEquals('foobar', resolve('cache')->get('user:foo@bar.com:name'));
    }

    /**
     * @test
     */
    public function itCanStoreResolverResultInPrivateCache()
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $cacheKey = "auth:{$user->getKey()}:user:1:name";

        $resolver = addslashes(self::class) . '@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache(private: true)
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";
        $query = '
        {
            user {
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foobar', array_get($result, 'data.user.name'));
        $this->assertEquals('foobar', resolve('cache')->get($cacheKey));
    }

    /**
     * @test
     */
    public function itCanStorePaginateResolverInCache()
    {
        factory(User::class, 5)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User] @paginate(type: "paginator", model: "User") @cache
        }
        ';
        $query = '
        {
            users(count: 5) {
                data {
                    id
                    name
                }
            }
        }
        ';
        $this->execute($schema, $query);

        $result = resolve('cache')->get('query:users:count:5');

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(5, $result);
    }

    /**
     * @test
     */
    public function itCanCacheHasManyResolver()
    {
        $user = factory(User::class)->create();

        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $schema = '
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
        $result = $this->execute($schema, $query)['data'];

        $posts = resolve('cache')->get("user:{$user->getKey()}:posts:count:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $queries = 0;
        \DB::listen(function ($query) use (&$queries) {
            // TODO: Find a better way of doing this
            if (!str_contains($query->sql, [
                'drop',
                'delete',
                'migrations',
                'aggregate',
                'limit 1',
            ])) {
                ++$queries;
            }
        });

        $cache = $this->execute($schema, $query)['data'];

        // Get the the original user and the `find` directive checks the count
        $this->assertEquals(0, $queries);
        $this->assertEquals($result, $cache);
    }

    /**
     * @test
     */
    public function itCanUseCustomCacheValue()
    {
        /** @var ValueFactory $valueFactory */
        $valueFactory = resolve(ValueFactory::class);
        $valueFactory->cacheResolver(function ($arguments) {
            return new class($arguments) extends CacheValue
            {
                public function getKey()
                {
                    return 'foo';
                }
            };
        });

        $resolver = addslashes(self::class) . '@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
        }
        
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";
        $query = '
        {
            user {
                name
            }
        }
        ';
        $this->execute($schema, $query);

        $this->assertEquals('foobar', resolve('cache')->get('foo'));
    }

    /**
     * @test
     */
    public function itCanAttachTagsToCache()
    {
        config(['lighthouse.cache.tags' => true]);

        $user = factory(User::class)->create();
        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $tags = ['graphql:user:1', 'graphql:user:1:posts'];

        $schema = '
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
        $result = $this->execute($schema, $query)['data'];

        $posts = resolve('cache')->tags($tags)->get("user:{$user->getKey()}:posts:count:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $queries = 0;
        \DB::listen(function ($query) use (&$queries) {
            // TODO: Find a better way of doing this
            if (!str_contains($query->sql, [
                'drop',
                'delete',
                'migrations',
                'aggregate',
                'limit 1',
            ])) {
                ++$queries;
            }
        });

        $cache = $this->execute($schema, $query)['data'];

        // Get the the original user and the `find` directive checks the count
        $this->assertEquals(0, $queries);
        $this->assertEquals($result, $cache);
    }

    public function resolve(): array
    {
        return [
            'id' => 1,
            'name' => 'foobar',
            'email' => 'foo@bar.com',
        ];
    }
}
