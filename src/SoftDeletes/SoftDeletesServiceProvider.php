<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Utils;

class SoftDeletesServiceProvider extends ServiceProvider
{
    /**
     * Ensure the model uses the SoftDeletes trait.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     *
     * @see \Illuminate\Database\Eloquent\SoftDeletes
     */
    public static function assertModelUsesSoftDeletes(string $modelClass, string $exceptionMessage): void
    {
        if (! Utils::classUsesTrait($modelClass, SoftDeletes::class)) {
            throw new DefinitionException($exceptionMessage);
        }
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        Parser::enumTypeDefinition('
                            "Specify if you want to include or exclude trashed results from a query."
                            enum Trashed {
                                "Only return trashed results."
                                ONLY @enum(value: "only")

                                "Return both trashed and non-trashed results."
                                WITH @enum(value: "with")

                                "Only return non-trashed results."
                                WITHOUT @enum(value: "without")
                            }
                        ')
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
