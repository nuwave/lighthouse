<?php

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class TestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MockResolverService::class);

        if (class_exists('Illuminate\Testing\TestResponse')) {
            \Illuminate\Testing\TestResponse::mixin(new TestResponseMixin());
        } elseif (class_exists('Illuminate\Foundation\Testing\TestResponse')) {
            \Illuminate\Foundation\Testing\TestResponse::mixin(new TestResponseMixin());
        }
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
