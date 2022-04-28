<?php

namespace Nuwave\Lighthouse\CacheControl;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class CacheControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheControl::class);
    }

    public function boot(Dispatcher $dispatcher, CacheControl $cacheControl): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            EndRequest::class,
            function (EndRequest $request) use ($cacheControl): void {
                $request->response->setCache($cacheControl->makeHeaderOptions());
                app()->forgetInstance(CacheControl::class);
            }
        );
    }
}
