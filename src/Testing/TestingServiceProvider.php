<?php

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class TestingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }

    public function register(): void
    {
        $this->app->singleton(MockDirective::class);

        if (class_exists('Illuminate\Testing\TestResponse')) {
            \Illuminate\Testing\TestResponse::mixin(new TestResponseMixin());
        } elseif (class_exists('Illuminate\Foundation\Testing\TestResponse')) {
            \Illuminate\Foundation\Testing\TestResponse::mixin(new TestResponseMixin());
        }
    }
}
