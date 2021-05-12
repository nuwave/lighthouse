<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\Parser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\ManipulateAST;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
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
                        "The available directions for ordering a list of records."
                        enum SortOrder {
                            "Sort records in ascending order."
                            ASC

                            "Sort records in descending order."
                            DESC
                        }
                    '
                    )
                );
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "TODO: description"
                        enum AggregateFunctionOrderWithoutColumn {
                            "Count function."
                            COUNT
                        }
                    '
                    )
                );
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "TODO: description"
                        enum AggregateFunctionOrder {
                            "avg function."
                            AVG

                            "min function."
                            MIN

                            "max function."
                            MAX

                            "sum function."
                            SUM

                            "count function."
                            COUNT

                        }
                    '
                    )
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

    public static function createOrderByRelationInput(string $name, string $description, string $relation, string $configurationType): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "TODO: description"
                $relation: $configurationType!

                "The direction that is used for ordering."
                order: SortOrder!
            }
GRAPHQL
        );
    }

    public static function createRelationConfigurationWithoutColumnsInput(string $name, string $description): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "TODO: description"
                aggregate: AggregateFunctionOrderWithoutColumn!
            }
GRAPHQL
        );
    }

    public static function createRelationConfigurationWithColumnsInput(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "TODO: description"
                aggregate: AggregateFunctionOrder!

                "TODO: description"
                column: $columnType
            }
GRAPHQL
        );
    }
}
