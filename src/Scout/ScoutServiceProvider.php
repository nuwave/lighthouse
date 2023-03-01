<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Scout;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ScoutServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            fn (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string => __NAMESPACE__
        );
    }
}
