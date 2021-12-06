<?php

namespace Nuwave\Lighthouse\Void;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

/**
 * TODO include by default in v6.
 */
class VoidServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            static function (ManipulateAST $manipulateAST): void {
                $manipulateAST->documentAST->setTypeDefinition(
                    Parser::scalarTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Always null."
scalar Null @scalar(class: "Nuwave\\\Lighthouse\\\Void\\\NullScalar")
GRAPHQL
)
                );
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }
}
