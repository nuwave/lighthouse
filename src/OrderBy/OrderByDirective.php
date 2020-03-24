<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

class OrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, DefinedDirective
{
    use GeneratesColumnsEnum;

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
    Mutually exclusive with the `columnsEnum` argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allowes you to re-use the same enum for multiple fields.
    Mutually exclusive with the `columns` argument.
    """
    columnsEnum: String
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
            $model = $builder instanceof Builder ? $builder->getModel() : null;

            if (Str::contains($orderByClause[config('lighthouse.orderBy')], '.') && $model) {
                $builder = $this->joinRelatedTable($builder, $orderByClause, $model);
            } else {
                // fully qualify the column
                if ($model) {
                    $orderByClause[config('lighthouse.orderBy')] = $model->qualifyColumn($orderByClause[config('lighthouse.orderBy')]);
                }

                $builder->orderBy(
                    // TODO deprecated in v5
                    $orderByClause[config('lighthouse.orderBy')],
                    $orderByClause['order']
                );
            }
        }

        return $builder;
    }

    private function isAlreadyJoined($builder, $table)
    {
        $existingJoins = $builder instanceof Builder ? $builder->toBase()->joins : $builder->joins;

        if (!is_array($existingJoins)) {
            return false;
        }

        foreach ($existingJoins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }

    private function joinRelatedTable($builder, $orderByClause, $model)
    {
        [$relation, $column] = explode('.', $orderByClause[config('lighthouse.orderBy')], 2);
        $builder = $builder instanceof Builder ? $builder : new Builder($builder);
        $builder->setModel(new $model);

        try {
            $relatedModel = $builder->getRelation($relation)->getModel();
        } catch(\Exception $e) {
            return $builder->orderBy(
                $orderByClause[config('lighthouse.orderBy')],
                $orderByClause['order']
            );
        }

        if (!$this->isAlreadyJoined($builder, $relatedModel->getTable())) {
            $builder->join($relatedModel->getTable(), $relatedModel->getForeignKey(), '=', $relatedModel->getQualifiedKeyName());
        }

        return $builder->orderBy(
            $relatedModel->qualifyColumn($column),
            $orderByClause['order']
        );
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
        if ($this->hasAllowedColumns()) {
            $restrictedOrderByName = $this->restrictedOrderByName($argDefinition, $parentField);
            $argDefinition->type = PartialParser::listType("[$restrictedOrderByName!]");
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField);

            $documentAST
                ->setTypeDefinition(
                    OrderByServiceProvider::createOrderByClauseInput(
                        $restrictedOrderByName,
                        "Order by clause for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
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
