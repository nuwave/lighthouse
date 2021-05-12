<?php

namespace Nuwave\Lighthouse\OrderBy;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;

class OrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, FieldBuilderDirective
{
    use GeneratesColumnsEnum;

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Sort a result list by one or more given columns.
"""
directive @orderBy(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with the `columnsEnum` argument.
    Only used when the directive is added on an argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allowes you to re-use the same enum for multiple fields.
    Mutually exclusive with the `columns` argument.
    Only used when the directive is added on an argument.
    """
    columnsEnum: String

    """
    TODO: description
    """
    relations: [OrderByRelation!]

    """
    The database column for which the order by clause will be applied on.
    Only used when the directive is added on a field.
    """
    column: String

    """
    The direction of the order by clause.
    Only used when the directive is added on a field.
    """
    direction: OrderByDirection = ASC
) on ARGUMENT_DEFINITION | FIELD_DEFINITION

"""
Options for the `direction` argument on `@orderBy`.
"""
enum OrderByDirection {
    """
    Sort in ascending order.
    """
    ASC

    """
    Sort in descending order.
    """
    DESC
}

"""
Configuration for sort using relation.
"""
input OrderByRelation {
    """
    TODO: description
    """
    relation: String!

    """
    TODO: description
    """
    columns: [String!]
}

GRAPHQL;
    }

    /**
     * @param  array<array{column: string, order: string}>  $value
     */
    public function handleBuilder($builder, $value): object
    {
        foreach ($value as $orderByClause) {
            $relationInfo = Arr::except($orderByClause, ['order', 'column']);

            if (! $relationMethod = array_keys($relationInfo)[0] ?? null) {
                $builder->orderBy(
                    $orderByClause['column'],
                    $orderByClause['order']
                );

                continue;
            }

            $aggregate = Str::lower(Arr::get($relationInfo, "{$relationMethod}.aggregate"));
            $column = Arr::get($relationInfo, "{$relationMethod}.column");

            if ($aggregate === 'count') {
                $builder->withCount($relationMethod);
                $orderColumn = Str::snake($relationMethod.' count');
            } else {
                $operator = Str::camel('with '.$aggregate);
                $builder->{$operator}($relationMethod, $column);

                $orderColumn = Str::snake("{$relationMethod} {$aggregate} {$column}");
            }

            $builder->orderBy(
                $orderColumn,
                $orderByClause['order']
            );
        }

        return $builder;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $orderByClauseName = OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE;

        $allowedColumnsEnumName = 'String!';

        if ($this->hasAllowedColumns()) {
            $restrictedOrderByName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType).'OrderByClause';
            $orderByClauseName = $restrictedOrderByName;
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);
        }

        if ($this->directiveHasArgument('relations')) {
            $relationsInputs = [];

            foreach ($this->directiveArgValue('relations') as $relation) {
                $restrictedOrderByNameRelation = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType).'OrderBy'.ucfirst($relation['relation']).'Relation';
                $relationsInputs[] = [
                    'input' => $restrictedOrderByNameRelation,
                    'relation' => $relation['relation'],
                ];
                $columns = $relation['columns'] ?? [];

                if (count($columns) > 0) {
                    $allowedColumnsEnumNameRelation = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType).ucfirst($relation['relation']).'Column';
                    $documentAST
                        ->setTypeDefinition(
                            $this->createAllowedColumnsEnum(
                                $argDefinition,
                                $parentField,
                                $parentType,
                                $columns,
                                $allowedColumnsEnumNameRelation
                            )
                        );

                    $documentAST
                        ->setTypeDefinition(
                            OrderByServiceProvider::createRelationConfigurationWithColumnsInput(
                                $restrictedOrderByNameRelation,
                                'TODO: description',
                                $allowedColumnsEnumNameRelation
                            )
                        );
                } else {
                    $documentAST
                        ->setTypeDefinition(
                            OrderByServiceProvider::createRelationConfigurationWithoutColumnsInput(
                                $restrictedOrderByNameRelation,
                                'TODO: description'
                            )
                        );
                }
            }

            $orderByClauseName = "{$orderByClauseName}WithRelation";

            $inputMerged = "
                \"TODO: description\"
                input {$orderByClauseName} {
                    \"The column that is used for ordering.\"
                    column: $allowedColumnsEnumName

                    \"The direction that is used for ordering.\"
                    order: SortOrder!";

            collect($relationsInputs)->each(function ($relation) use (&$inputMerged) {
                $inputMerged .= "
                    \"TODO: description\"
                    {$relation['relation']}: {$relation['input']}
                ";
            });

            $documentAST->setTypeDefinition(Parser::inputObjectTypeDefinition($inputMerged.'}'));
        } else {
            $documentAST
                ->setTypeDefinition(
                    OrderByServiceProvider::createOrderByClauseInput(
                        $restrictedOrderByName,
                        "Order by clause for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                );
        }

        $argDefinition->type = Parser::typeReference('['.$orderByClauseName.'!]');
    }

    public function handleFieldBuilder(object $builder): object
    {
        return $builder->orderBy(
            $this->directiveArgValue('column'),
            $this->directiveArgValue('direction', 'ASC')
        );
    }
}
