<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\Utils\Models\User;

trait TestsSerialization
{
    protected function fakeContextSerializer(): void
    {
        $contextSerializer = new class() implements ContextSerializer {
            public function serialize(GraphQLContext $context)
            {
                return 'foo';
            }

            public function unserialize(string $context)
            {
                return new class() implements GraphQLContext {
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

        Container::getInstance()->instance(ContextSerializer::class, $contextSerializer);
    }

    protected function useSerializingArrayStore(): void
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $config->set('cache.stores.array.serialize', true);
    }
}
