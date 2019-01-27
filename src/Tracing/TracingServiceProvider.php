<?php

namespace Nuwave\Lighthouse\Defer;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\StartRequest;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Tracing\Tracing;
use Nuwave\Lighthouse\Tracing\TracingDirective;

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
            Tracing::class
        );

        $dispatcher->listen(
            ManipulatingAST::class,
            Tracing::class . '@handleManipulatingAST'
        );

        $dispatcher->listen(
            StartRequest::class,
            Tracing::class . '@handleStartRequest'
        );

        $dispatcher->listen(
            StartExecution::class,
            Tracing::class . '@handleStartExecution'
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
