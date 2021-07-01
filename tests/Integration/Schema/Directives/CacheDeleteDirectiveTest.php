<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class CacheDeleteDirectiveTest extends DBTestCase
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
            user: User @deleteCache(key: "user2")
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

        $this->assertNotSame('foobar', $this->cache->get('user2'));
    }
}
