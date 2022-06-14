<?php

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Support\Contracts\CacheKeyAndTags;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CacheKeyAndTags::class, GenerateCacheKeyAndTags::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }
}
