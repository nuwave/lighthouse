<?php

namespace Nuwave\Lighthouse\CacheControl;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class CacheControlServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CacheControl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $documentAST = $manipulateAST->documentAST;
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "Options for the `scope` argument of `@cacheControl`."
                        enum CacheControlScope {
                            "The HTTP Cache-Control header set to public."
                            PUBLIC @enum(value: "public")

                            "The HTTP Cache-Control header set to private."
                            PRIVATE @enum(value: "private")
                        }
                    ')
                );
            }
        );
    }
}
