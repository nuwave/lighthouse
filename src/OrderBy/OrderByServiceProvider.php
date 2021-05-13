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
                        enum AggregateFunctionOrder {
                            "TODO: description"
                            COUNT
                        }
                    '
                    )
                );
                $documentAST->setTypeDefinition(
                    Parser::enumTypeDefinition(/* @lang GraphQL */ '
                        "TODO: description"
                        enum AggregateFunctionOrderForColumn {
                            "TODO: description"
                            AVG

                            "TODO: description"
                            MIN

                            "TODO: description"
                            MAX

                            "TODO: description"
                            SUM

                            "TODO: description"
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

    public static function createOrderByRelationClauseInput(string $name, string $description, string $relation, string $configurationType): InputObjectTypeDefinitionNode
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

    public static function createRelationAggregateFunctionInput(string $name, string $description): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "TODO: description"
                aggregate: AggregateFunctionOrder!
            }
GRAPHQL
        );
    }

    public static function createRelationAggregateFunctionForColumnInput(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "TODO: description"
                aggregate: AggregateFunctionOrderForColumn!

                "TODO: description"
                column: $columnType
            }
GRAPHQL
        );
    }
}
