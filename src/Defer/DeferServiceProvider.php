<?php

namespace Nuwave\Lighthouse\Defer;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;

class DeferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Defer::class);
        $this->app->singleton(CreatesResponse::class, Defer::class);
    }

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
                $this->handleManipulateAST($manipulateAST);
            }
        );

        $dispatcher->listen(
            StartExecution::class,
            Defer::class . '@handleStartExecution'
        );
    }

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     */
    public function handleManipulateAST(ManipulateAST $manipulateAST): void
    {
        ASTHelper::attachDirectiveToObjectTypeFields(
            $manipulateAST->documentAST,
            Parser::constDirective(/** @lang GraphQL */ '@deferrable')
        );

        $manipulateAST->documentAST->setDirectiveDefinition(
            Parser::directiveDefinition(/** @lang GraphQL */ '
"""
Use this directive on expensive or slow fields to resolve them asynchronously.
Must not be placed upon:
- Non-Nullable fields
- Mutation root fields
"""
directive @defer(if: Boolean = true) on FIELD
')
        );
    }
}
