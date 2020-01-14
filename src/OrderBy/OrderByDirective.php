<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\Codegen;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class OrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Sort a result list by one or more given columns.
"""
directive @orderBy(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    """
    columns: [String!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Apply an "ORDER BY" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string[]  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        foreach ($value as $orderByClause) {
            $builder->orderBy(
                // TODO deprecated in v5
                $orderByClause[config('lighthouse.orderBy')],
                $orderByClause['order']
            );
        }

        return $builder;
    }

    /**
     * Validate the input argument definition.
     *
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\InputValueDefinitionNode $argDefinition
     * @param \GraphQL\Language\AST\FieldDefinitionNode $parentField
     * @param \GraphQL\Language\AST\ObjectTypeDefinitionNode $parentType
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        if ($allowedColumns = $this->directiveArgValue('columns')) {
            $restrictedOrderByName = $this->restrictedOrderByName($argDefinition, $parentField);
            $argDefinition->type = PartialParser::listType("[$restrictedOrderByName!]");

            $allowedColumnsEnumName = Codegen::allowedColumnsEnumName($argDefinition, $parentField);

            $documentAST
                ->setTypeDefinition(
                    OrderByServiceProvider::createOrderByClauseInput(
                        $restrictedOrderByName,
                        "Order by clause for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                )
                ->setTypeDefinition(
                    Codegen::createAllowedColumnsEnum($argDefinition, $parentField, $allowedColumns, $allowedColumnsEnumName)
                );
        } else {
            $argDefinition->type = PartialParser::listType('['.OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE.'!]');
        }
    }

    /**
     * Create the name for the restricted OrderByClause input.
     *
     * @example FieldNameArgNameOrderByClause
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $argDefinition
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $parentField
     * @return string
     */
    protected function restrictedOrderByName(InputValueDefinitionNode &$argDefinition, FieldDefinitionNode &$parentField): string
    {
        return Str::studly($parentField->name->value)
            .Str::studly($argDefinition->name->value)
            .'OrderByClause';
    }
}
