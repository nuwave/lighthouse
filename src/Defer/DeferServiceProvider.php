<?php

namespace Nuwave\Lighthouse\Defer;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;

class DeferServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(DirectiveFactory $directiveFactory, Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            ManipulateAST::class,
            Defer::class.'@handleManipulateAST'
        );
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Defer::class);

        $this->app->singleton(CreatesResponse::class, Defer::class);
    }
}
