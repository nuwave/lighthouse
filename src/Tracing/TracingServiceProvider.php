<?php

namespace Nuwave\Lighthouse\Tracing;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tracing::class);
    }

    public function boot(EventsDispatcher $eventsDispatcher): void
    {
        $eventsDispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $tracingDirective = Parser::constDirective('@tracing');
        $eventsDispatcher->listen(
            ManipulateAST::class,
            static function (ManipulateAST $manipulateAST) use ($tracingDirective): void {
                ASTHelper::attachDirectiveToObjectTypeFields($manipulateAST->documentAST, $tracingDirective);
            }
        );

        $eventsDispatcher->listen(
            StartExecution::class,
            Tracing::class . '@handleStartExecution'
        );

        $eventsDispatcher->listen(
            BuildExtensionsResponse::class,
            Tracing::class . '@handleBuildExtensionsResponse'
        );
    }
}
