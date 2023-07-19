<?php declare(strict_types=1);

namespace Tests\Integration\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\Validator as SchemaValidator;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class CacheDirectiveTest extends DBTestCase
{
    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->cache = $app->make(CacheRepository::class);
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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:1:name'));
    }

    public function testCacheKeyIsValidOnFieldDefinition(): void
    {
        $this->schema = /** @lang GraphQL */ '
            type User {
                id: ID!
                name: String @cache
                email: String @cacheKey
            }

            type Query {
                user: User @first
            }
        ';

        $schemaValidator = $this->app->make(SchemaValidator::class);

        $schemaValidator->validate();

        $this->expectNotToPerformAssertions();
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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:foo@bar.com:name'));
    }

    public function testCacheKeyWithRenameDirective(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
            'email_name' => 'foo@bar.com',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String @cache
            emailName: String @cacheKey @rename(attribute: "email_name")
        }

        type Query {
            user: User @mock
        }
        ';

        $response = $this->graphQL(/** @lang GraphQL */ '
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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:foo@bar.com:name'));
    }

    public function testIDCacheKeyWithRenameDirective(): void
    {
        $this->mockResolver([
            'id_' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID! @rename(attribute: "id_")
            name: String @cache
        }

        type Query {
            user: User @mock
        }
        ';

        $response = $this->graphQL(/** @lang GraphQL */ '
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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:1:name'));
    }

    public function testStoreResolverResultInPrivateCache(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);
        $cacheKey = "lighthouse:auth:{$user->getKey()}:User:1:name";

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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:1:name'));
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

        $this->assertSame('foobar', $this->cache->get('lighthouse:User:1:name'));
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
                paginatorInfo {
                    total
                }
                data {
                    id
                    name
                }
            }
        }
        ');

        $result = $this->cache->get('lighthouse:Query::users:first:5');

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
        query ($id: ID!) {
            user(id: $id) {
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
        DB::listen(static function (QueryExecuted $query) use (&$dbQueryCountForPost): void {
            if (Str::contains($query->sql, 'select * from `posts`')) {
                ++$dbQueryCountForPost;
            }
        });

        $firstResponse = $this->graphQL($query, [
            'id' => $user->getKey(),
        ]);

        $posts = $this->cache->get("lighthouse:User:{$user->getKey()}:posts:first:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $cachedResponse = $this->graphQL($query, [
            'id' => $user->getKey(),
        ]);

        $this->assertSame(1, $dbQueryCountForPost, 'This query should only run once and be cached on the second run.');
        $this->assertSame(
            $firstResponse->json(),
            $cachedResponse->json(),
        );
    }

    public function testAttachTagsToCache(): void
    {
        config(['lighthouse.cache_directive_tags' => true]);

        $user = factory(User::class)->create();
        factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        $tags = ['lighthouse:User:1', 'lighthouse:User:1:posts'];

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
            user(id: ID! @eq): User @find(model: "User") @cache
        }
        ';

        $query = /** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
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
        DB::listen(static function (QueryExecuted $query) use (&$dbQueryCountForPost): void {
            if (Str::contains($query->sql, 'select * from `posts`')) {
                ++$dbQueryCountForPost;
            }
        });

        $firstResponse = $this->graphQL($query, [
            'id' => $user->getKey(),
        ]);

        $posts = $this->cache
            ->tags($tags)
            ->get("lighthouse:User:{$user->getKey()}:posts:first:3");
        $this->assertInstanceOf(LengthAwarePaginator::class, $posts);
        $this->assertCount(3, $posts);

        $cachedResponse = $this->graphQL($query, [
            'id' => $user->getKey(),
        ]);

        $this->assertSame(1, $dbQueryCountForPost, 'This query should only run once and be cached on the second run.');
        $this->assertSame(
            $firstResponse->json(),
            $cachedResponse->json(),
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

        $this->cache->setMultiple([
            'lighthouse:User:1:field_boolean' => false,
            'lighthouse:User:1:field_string' => '',
            'lighthouse:User:1:field_integer' => 0,
        ]);

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
