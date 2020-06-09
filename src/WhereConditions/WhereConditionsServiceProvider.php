<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class WhereConditionsServiceProvider extends ServiceProvider
{
    public const DEFAULT_HAS_OPERATOR = 'GTE';

    public const DEFAULT_HAS_AMOUNT = 1;

    public const DEFAULT_WHERE_CONDITIONS = 'WhereConditions';

    public const DEFAULT_WHERE_RELATION_CONDITIONS = 'Relation';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Operator::class, SQLOperator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
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
                /** @var \Nuwave\Lighthouse\WhereConditions\Operator $operator */
                $operator = $this->app->make(Operator::class);

                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        static::createWhereConditionsInputType(
                            static::DEFAULT_WHERE_CONDITIONS,
                            'Dynamic WHERE conditions for queries.',
                            'String'
                        )
                    )
                    ->setTypeDefinition(
                        static::createHasConditionsInputType(
                            static::DEFAULT_WHERE_CONDITIONS,
                            'Dynamic HAS conditions for WHERE condition queries.'
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

    public static function createWhereConditionsInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        $hasRelationInputName = $name . self::DEFAULT_WHERE_RELATION_CONDITIONS;

        /** @var \Nuwave\Lighthouse\WhereConditions\Operator $operator */
        $operator = app(Operator::class);

        $operatorName = PartialParser
            ::enumTypeDefinition(
                $operator->enumDefinition()
            )
            ->name
            ->value;
        $operatorDefault = $operator->default();

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "The column that is used for the condition."
                column: $columnType

                "The operator that is used for the condition."
                operator: $operatorName = $operatorDefault

                "The value that is used for the condition."
                value: Mixed

                "A set of conditions that requires all conditions to match."
                AND: [$name!]

                "A set of conditions that requires at least one condition to match."
                OR: [$name!]

                "A condition for chacking relations."
                HAS: $hasRelationInputName
            }
GRAPHQL
        );
    }

    public static function createHasConditionsInputType(string $name, string $description): InputObjectTypeDefinitionNode
    {
        $hasRelationInputName = $name . self::DEFAULT_WHERE_RELATION_CONDITIONS;
        $default_has_amount   = WhereConditionsServiceProvider::DEFAULT_HAS_AMOUNT;
        $default_has_operator = WhereConditionsServiceProvider::DEFAULT_HAS_OPERATOR;

        $operatorName = PartialParser::enumTypeDefinition(
            app(Operator::class)->enumDefinition()
        )->name->value;

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $hasRelationInputName {
                "The relation that is checked."
                relation: String!

                "The comparision operator to test aginst the amount."
                operator: $operatorName = $default_has_operator

                "The amount to test."
                amount: Int = $default_has_amount

                "Additional condition logic."
                condition: $name
            }
GRAPHQL
        );
    }
}
