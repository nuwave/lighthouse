<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ValidationServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }
}
