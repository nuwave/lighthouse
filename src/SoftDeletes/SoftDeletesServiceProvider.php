<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class SoftDeletesServiceProvider extends ServiceProvider
{
    /**
     * Ensure a model uses the SoftDeletes trait.
     *
     * @param  string  $modelClass
     * @param  string  $exceptionMessage
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     * @see \Illuminate\Database\Eloquent\SoftDeletes
     */
    public static function assertModelUsesSoftDeletes(string $modelClass, string $exceptionMessage): void
    {
        if (
            ! in_array(
                SoftDeletes::class,
                class_uses_recursive($modelClass)
            )
        ) {
            throw new DefinitionException($exceptionMessage);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        PartialParser::enumTypeDefinition('
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
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );
    }
}
