<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Void;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

/**
 * TODO include by default in v7.
 */
class VoidServiceProvider extends ServiceProvider
{
    public const VOID = 'VOID';

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            static function (ManipulateAST $manipulateAST): void {
                $void = self::VOID;
                $manipulateAST->documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"""
Allows only one value and thus can hold no information.

https://en.wikipedia.org/wiki/Unit_type
"""
enum Void {
  "The only possible value."
  {$void}
}
GRAPHQL
                    ),
                );
            },
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static fn (): string => __NAMESPACE__,
        );
    }
}
