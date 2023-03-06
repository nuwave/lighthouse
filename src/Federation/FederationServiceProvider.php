<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use Illuminate\Contracts\Events\Dispatcher;
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

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__ . '\\Directives');
        $dispatcher->listen(ManipulateAST::class, ASTManipulator::class);
        $dispatcher->listen(ValidateSchema::class, SchemaValidator::class);
    }
}
