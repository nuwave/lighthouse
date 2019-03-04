<?php

namespace Nuwave\Lighthouse\Tracing;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\StartRequest;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Events\GatheringExtensions;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class TracingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(DirectiveFactory $directiveFactory, Dispatcher $dispatcher): void
    {
        $directiveFactory->addResolved(
            TracingDirective::NAME,
            TracingDirective::class
        );

        $dispatcher->listen(
            ManipulatingAST::class,
            Tracing::class.'@handleManipulatingAST'
        );

        $dispatcher->listen(
            StartRequest::class,
            Tracing::class.'@handleStartRequest'
        );

        $dispatcher->listen(
            StartExecution::class,
            Tracing::class.'@handleStartExecution'
        );

        $dispatcher->listen(
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
