<?php

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;

class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tracing::class);
    }

    public function boot(EventsDispatcher $eventsDispatcher): void
    {
        $eventsDispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $eventsDispatcher->listen(
            ManipulateAST::class,
            Tracing::class.'@handleManipulateAST'
        );

        $eventsDispatcher->listen(
            StartRequest::class,
            Tracing::class.'@handleStartRequest'
        );

        $eventsDispatcher->listen(
            StartExecution::class,
            Tracing::class.'@handleStartExecution'
        );

        $eventsDispatcher->listen(
            BuildExtensionsResponse::class,
            Tracing::class.'@handleBuildExtensionsResponse'
        );
    }
}
