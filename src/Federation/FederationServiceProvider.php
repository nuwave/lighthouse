<?php

namespace Nuwave\Lighthouse\Federation;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\ValidateSchema;

class FederationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntityResolverProvider::class);
    }

    public function boot(EventsDispatcher $eventsDispatcher): void
    {
        $eventsDispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__ . '\\Directives';
            }
        );

        $eventsDispatcher->listen(ManipulateAST::class, ASTManipulator::class);
        $eventsDispatcher->listen(ValidateSchema::class, SchemaValidator::class);
    }
}
