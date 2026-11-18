<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Select;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class SelectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SelectColumns::class);
    }

    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
        // Runs before the ManipulateAST listeners of later providers, so fields they add are not validated
        $dispatcher->listen(ManipulateAST::class, SelectValidator::class);
    }
}
