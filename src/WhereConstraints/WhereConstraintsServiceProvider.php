<?php

namespace Nuwave\Lighthouse\WhereConstraints;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class WhereConstraintsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(DirectiveFactory $directiveFactory, Dispatcher $dispatcher): void
    {
        $directiveFactory->addResolved(
            WhereConstraintsDirective::NAME,
            WhereConstraintsDirective::class
        );

        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $manipulateAST->documentAST
                    ->setDefinition(
                        PartialParser::inputObjectTypeDefinition('
                            input WhereConstraints {
                                column: String
                                operator: Operator = EQ
                                value: Mixed
                                AND: [WhereConstraints!]
                                OR: [WhereConstraints!]
                                NOT: [WhereConstraints!]
                            }
                        ')
                    )
                    ->setDefinition(
                        PartialParser::scalarTypeDefinition('
                            scalar Mixed @scalar(class: "MLL\\\GraphQLScalars\\\Mixed")
                        ')
                    );
            }
        );
    }
}
