<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class TestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MockResolverService::class);
    }

    public function boot(EventsDispatcher $dispatcher): void
    {
        TestResponse::mixin(new TestResponseMixin());
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }
}
