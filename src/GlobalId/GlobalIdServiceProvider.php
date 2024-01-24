<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class GlobalIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GlobalId::class, Base64GlobalId::class);
        $this->app->singleton(NodeRegistry::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }
}
