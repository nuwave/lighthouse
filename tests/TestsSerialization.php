<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
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
            public function serialize(GraphQLContext $context): string
            {
                return 'foo';
            }

            public function unserialize(string $context): GraphQLContext
            {
                return new class() implements GraphQLContext {
                    public function user(): User
                    {
                        return new User();
                    }

                    public function setUser(?Authenticatable $user): void {}

                    public function request(): Request
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
        $config->set('cache.stores.array.serialize', true);
    }
}
