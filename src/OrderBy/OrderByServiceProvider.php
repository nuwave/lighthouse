<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class OrderByServiceProvider extends ServiceProvider
{
    public const DEFAULT_ORDER_BY_CLAUSE = 'OrderByClause';

    /**
     * Bootstrap any application services.
     */
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
                $documentAST = $manipulateAST->documentAST;
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "Directions for ordering a list of records."
                        enum SortOrder {
                            "Sort records in ascending order."
                            ASC

                            "Sort records in descending order."
                            DESC
                        }
                    ')
                );
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "Aggregate functions when ordering by a relation without specifying a column."
                        enum OrderByRelationAggregateFunction {
                            "Amount of items."
                            COUNT @enum(value: "count")
                        }
                    ')
                );
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "Aggregate functions when ordering by a relation that may specify a column."
                        enum OrderByRelationWithColumnAggregateFunction {
                            "Average."
                            AVG @enum(value: "avg")

                            "Minimum."
                            MIN @enum(value: "min")

                            "Maximum."
                            MAX @enum(value: "max")

                            "Sum."
                            SUM @enum(value: "sum")

                            "Amount of items."
                            COUNT @enum(value: "count")
                        }
                    ')
                );
                $documentAST->setTypeDefinition(
                    static::createOrderByClauseInput(
                        static::DEFAULT_ORDER_BY_CLAUSE,
                        'Allows ordering a list of records.',
                        'String'
                    )
                );
            }
        );
    }

    public static function createOrderByClauseInput(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "The column that is used for ordering."
                column: $columnType!

                "The direction that is used for ordering."
                order: SortOrder!
            }
GRAPHQL
        );
    }

    /**
     * We generate this in the same general shape as the input object with columns,
     * even though it is unnecessarily complex for this specific case, to make it
     * a non-breaking change when columns are added.
     */
    public static function createRelationAggregateFunctionInput(string $name, string $description): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "Always COUNT."
                aggregate: OrderByRelationAggregateFunction!
            }
GRAPHQL
        );
    }

    public static function createRelationAggregateFunctionForColumnInput(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "The aggregate function to apply to the column."
                aggregate: OrderByRelationWithColumnAggregateFunction!

                "Name of the column to use."
                column: $columnType
            }
GRAPHQL
        );
    }
}
