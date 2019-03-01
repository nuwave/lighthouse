<?php

namespace Nuwave\Lighthouse\Defer;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class DeferServiceProvider extends ServiceProvider
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
            DeferrableDirective::NAME,
            DeferrableDirective::class
        );

        $dispatcher->listen(
            ManipulatingAST::class,
            Defer::class . '@handleManipulatingAST'
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Defer::class);
    }
}
