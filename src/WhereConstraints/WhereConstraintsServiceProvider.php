<?php

namespace Nuwave\Lighthouse\WhereConstraints;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
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
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        static::createWhereConstraintsInputType(
                            'WhereConstraints',
                            'Dynamic WHERE constraints for queries.',
                            'String'
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::scalarTypeDefinition('
                            scalar Mixed @scalar(class: "MLL\\\GraphQLScalars\\\Mixed")
                        ')
                    );
            }
        );
    }

    public static function createWhereConstraintsInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        return PartialParser::inputObjectTypeDefinition("
            input $name {
                column: $columnType
                operator: Operator = EQ
                value: Mixed
                AND: [$name!]
                OR: [$name!]
                NOT: [$name!]
            }
        ");
    }
}
