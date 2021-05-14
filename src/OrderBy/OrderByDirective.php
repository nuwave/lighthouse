<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

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
     * @param  array<mixed>  $value
     */
    public function handleBuilder($builder, $value): object
    {
        foreach ($value as $orderByClause) {
            $relationMethod = $this->retrieveRelationNameIfExists($orderByClause);

            if (! $relationMethod || $builder instanceof \Illuminate\Database\Query\Builder) {
                $builder->orderBy(
                    $orderByClause['column'],
                    $orderByClause['order']
                );

                continue;
            }

            [$aggregate, $column] = $this->retrieveAggregateAndColumnParams($orderByClause, $relationMethod);

            if ($aggregate === 'count') {
                $builder->withCount($relationMethod);
                $orderColumn = Str::snake("{$relationMethod}_count");
            } else {
                $operator = Str::camel("with {$aggregate}");
                $builder->{$operator}($relationMethod, $column);

                $orderColumn = Str::snake("{$relationMethod}_{$aggregate}_{$column}");
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
        if (! $this->hasAllowedColumns() && ! $this->directiveHasArgument('relations')) {
            $argDefinition->type = Parser::typeReference('['.OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE.'!]');

            return;
        }

        $allowedColumnsEnumName = 'String!';
        $restrictedOrderByPrefix = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType);

        if ($this->hasAllowedColumns()) {
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);
        }

        if ($this->directiveHasArgument('relations')) {
            $relationsInputs = [];

            foreach ($this->directiveArgValue('relations', []) as $relation) {
                $restrictedOrderByNameRelation = $restrictedOrderByPrefix
                    .Str::ucfirst($relation['relation']);

                $relationsInputs[] = [
                    'input' => $restrictedOrderByNameRelation,
                    'relation' => Arr::get($relation, 'relation'),
                ];

                $columns = Arr::get($relation, 'columns', []);

                if (count($columns) > 0) {
                    $allowedColumnsEnumNameRelation = $restrictedOrderByPrefix
                        .Str::ucfirst($relation['relation'])
                        .'Column';

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
                            OrderByServiceProvider::createRelationAggregateFunctionForColumnInput(
                                $restrictedOrderByNameRelation,
                                'TODO: description',
                                $allowedColumnsEnumNameRelation
                            )
                        );
                } else {
                    $documentAST
                        ->setTypeDefinition(
                            OrderByServiceProvider::createRelationAggregateFunctionInput(
                                $restrictedOrderByNameRelation,
                                'TODO: description'
                            )
                        );
                }
            }

            $restrictedRelationOrderByName = $restrictedOrderByPrefix.'RelationOrderByClause';
            $nullableAllowedColumnsEnumName = Str::endsWith($allowedColumnsEnumName, '!')
                ? Str::replaceLast('!', '', $allowedColumnsEnumName)
                : $allowedColumnsEnumName;

            $inputMerged = "
                \"TODO: description\"
                input {$restrictedRelationOrderByName} {
                    \"The column that is used for ordering.\"
                    column: $nullableAllowedColumnsEnumName

                    \"The direction that is used for ordering.\"
                    order: SortOrder!";

            foreach ($relationsInputs as $key => $relation) {
                $inputMerged .= "
                    \"TODO: description\"
                    {$relation['relation']}: {$relation['input']}
                ";
            }

            $argDefinition->type = Parser::typeReference('['.$restrictedRelationOrderByName.'!]');

            $documentAST->setTypeDefinition(Parser::inputObjectTypeDefinition($inputMerged.'}'));
        } else {
            $restrictedOrderByName = $restrictedOrderByPrefix.'OrderByClause';
            $argDefinition->type = Parser::typeReference('['.$restrictedOrderByName.'!]');

            $documentAST
                ->setTypeDefinition(
                    OrderByServiceProvider::createOrderByClauseInput(
                        $restrictedOrderByName,
                        "Order by clause for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                );
        }
    }

    public function handleFieldBuilder(object $builder): object
    {
        return $builder->orderBy(
            $this->directiveArgValue('column'),
            $this->directiveArgValue('direction', 'ASC')
        );
    }

    /**
     * Get relation method using array key if exists.
     *
     * @param array<mixed> $orderByClause
     */
    protected function retrieveRelationNameIfExists(array $orderByClause): ?string
    {
        $relationInfo = Arr::except($orderByClause, ['order', 'column']);

        return Arr::first(array_keys($relationInfo));
    }

    /**
     * Get aggregate and column params from relation input.
     *
     * @param array<mixed> $orderByClause
     * @return array{mixed, mixed}
     */
    protected function retrieveAggregateAndColumnParams(array $orderByClause, string $relationMethod): array
    {
        $aggregate = Arr::get($orderByClause, "{$relationMethod}.aggregate");
        $column = Arr::get($orderByClause, "{$relationMethod}.column");

        return [
            Str::lower($aggregate),
            $column,
        ];
    }
}
