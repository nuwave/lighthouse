<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );
    }
}
