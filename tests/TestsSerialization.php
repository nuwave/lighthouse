<?php

namespace Tests;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\Utils\Models\User;

trait TestsSerialization
{
    protected function fakeContextSerializer(Container $app): void
    {
        $contextSerializer = new class implements ContextSerializer {
            public function serialize(GraphQLContext $context)
            {
                return 'foo';
            }

            public function unserialize(string $context)
            {
                return new class implements GraphQLContext {
                    public function user()
                    {
                        return new User();
                    }

                    public function request()
                    {
                        return new Request();
                    }
                };
            }
        };

        $app->instance(ContextSerializer::class, $contextSerializer);
    }

    protected function useSerializingArrayStore(Container $app): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        /** @var \Illuminate\Cache\CacheManager $cache */
        $cache = $app->make(CacheManager::class);
        $cache->extend('serializing-array', function () {
            return new Repository(
                new SerializingArrayStore()
            );
        });
        $config->set('cache.stores.array.driver', 'serializing-array');
    }
}
