<?php

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Events\GatheringExtensions;
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
            ManipulatingAST::class,
            Tracing::class.'@handleManipulatingAST'
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
            GatheringExtensions::class,
            Tracing::class.'@handleGatheringExtensions'
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
