<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class PaginationServiceProvider extends ServiceProvider
{
    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }
}
