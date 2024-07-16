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
        TestResponse::mixin(new TestResponseMixin());
    }

    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }
}
