<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Deferred;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;

class CacheDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanStoreResolverResultInCache()
    {
        $resolver = addslashes(self::class).'@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
        }
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $result = $this->execute($schema, '{ user { name } }');
        $this->assertEquals('foobar', array_get($result->data, 'user.name'));
        $this->assertEquals('foobar', app('cache')->get('user:1:name'));
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
        }';

        $query = '{
            users(count: 5) {
                data {
                    id
                    name
                }
            }
        }';

        $this->execute($schema, $query, true);

        $result = app('cache')->get('query::users:count:5');

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
        }';

        $query = '{
            user(id: '.$user->getKey().') {
                id
                name
                posts(count: 3) {
                    data {
                        title
                    }
                }
            }
        }';

        $this->execute($schema, $query, true);

        $posts = app('cache')->get("user:{$user->getKey()}:posts:count:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);
    }

    /**
     * @test
     */
    public function itCanUseCustomCacheValue()
    {
        $resolver = addslashes(self::class).'@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
        }
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        /** @var ValueFactory $valueFactory */
        $valueFactory = app(ValueFactory::class);
        $valueFactory->cacheResolver(function (...$args) {
            return new class(...$args) extends CacheValue {
                public function getKey()
                {
                    return 'foo';
                }
            };
        });

        $this->execute($schema, '{ user { name } }');
        $this->assertEquals('foobar', app('cache')->get('foo'));
    }

    /**
     * @test
     */
    public function itCanAttachTagsToCache()
    {
        config(['lighthouse.cache.tags' => true]);

        $tags = ['graphql:user:1', 'graphql:user:name:1'];
        $resolver = addslashes(self::class).'@resolve';
        $schema = "
        type User {
            id: ID!
            name: String @cache
        }
        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $cache = app('cache');
        $result = $this->execute($schema, '{ user { name } }');
        $this->assertEquals('foobar', array_get($result->data, 'user.name'));
        $this->assertEquals('foobar', $cache->tags($tags)->get('user:1:name'));

        $cache->tags($tags[0])->flush();
        $this->assertNull($cache->tags($tags)->get('user:1:name'));
    }

    public function resolve()
    {
        return [
            'id' => 1,
            'name' => 'foobar',
        ];
    }
}
