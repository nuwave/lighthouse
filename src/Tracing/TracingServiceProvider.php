<?php

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

class TracingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @return void
     */
    public function boot(DirectiveFactory $directiveFactory, EventsDispatcher $eventsDispatcher): void
    {
        $directiveFactory->addResolved(
            TracingDirective::NAME,
            TracingDirective::class
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

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Tracing::class);
    }
}
