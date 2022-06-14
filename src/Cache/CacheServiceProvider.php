<?php

namespace Nuwave\Lighthouse\Cache;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CacheKeyAndTags::class, CacheKeyAndTagsGenerator::class);
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
