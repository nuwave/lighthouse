<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class SetCacheDirectiveTest extends DBTestCase
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

    public function testCacheSetWithOutKey(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
        }

        type Query {
            user: User @setCache
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

        $this->assertSame('foobar', $this->cache->get('user'));
    }

    public function testCacheSetWithKey(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
            'email' => 'foo@bar.com',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
            email: String
        }

        type Query {
            user: User @setCache(key: "user2")
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

        $this->assertSame('foobar', $this->cache->get('user2'));
    }
}
