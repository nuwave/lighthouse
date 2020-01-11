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
    const DEFAULT_WHERE_CONSTRAINTS = 'WhereConstraints';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Operator::class, SQLOperator::class);
    }

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
                /** @var \Nuwave\Lighthouse\WhereConstraints\Operator $operator */
                $operator = $this->app->make(Operator::class);

                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        static::createWhereConstraintsInputType(
                            static::DEFAULT_WHERE_CONSTRAINTS,
                            'Dynamic WHERE constraints for queries.',
                            'String'
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::enumTypeDefinition(
                            $operator->enumDefinition()
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
                            scalar Mixed @scalar(class: "MLL\\\GraphQLScalars\\\Mixed")
                        ')
                    );
            }
        );
    }

    public static function createWhereConstraintsInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        /** @var \Nuwave\Lighthouse\WhereConstraints\Operator $operator */
        $operator = app(Operator::class);

        $operatorName = PartialParser
            ::enumTypeDefinition(
                $operator->enumDefinition()
            )
            ->name
            ->value;
        $operatorDefault = $operator->default();

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ "
            \"$description\"
            input $name {
                \"The column that is used for the constraint.\"
                column: $columnType

                \"The operator that is used for the constraint.\"
                operator: $operatorName = $operatorDefault

                \"The value that is used for the constraint.\"
                value: Mixed

                \"A set of constraints that requires all constraints to match.\"
                AND: [$name!]

                \"A set of constraints that requires at least one constraint to match.\"
                OR: [$name!]
            }
        ");
    }
}
