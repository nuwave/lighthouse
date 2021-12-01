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
    public const UNIT = 'UNIT';

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            static function (ManipulateAST $manipulateAST): void {
                $unit = self::UNIT;
                $manipulateAST->documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Allows only one value and thus can hold no information."
enum Unit {
  "The only possible value."
  {$unit}
}
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
