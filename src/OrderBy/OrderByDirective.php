<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

class OrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, FieldBuilderDirective
{
    use GeneratesColumnsEnum;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sort a result list by one or more given columns.
"""
directive @orderBy(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with `columnsEnum`.
    Only used when the directive is added on an argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allows you to re-use the same enum for multiple fields.
    Mutually exclusive with `columns`.
    Only used when the directive is added on an argument.
    """
    columnsEnum: String

    """
    Allow clients to sort by aggregates on relations.
    Only used when the directive is added on an argument.
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
Options for the `direction` argument of `@orderBy`.
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
Options for the `relations` argument of `@orderBy`.
"""
input OrderByRelation {
    """
    Name of the relation.
    """
    relation: String!

    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with `columnsEnum`.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allows you to re-use the same enum for multiple fields.
    Mutually exclusive with `columns`.
    """
    columnsEnum: String
}
GRAPHQL;
    }

    /** @param  array<array<string, mixed>>  $value */
    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        foreach ($value as $orderByClause) {
            $order = Arr::pull($orderByClause, 'order');
            $column = Arr::pull($orderByClause, 'column');

            if ($column === null) {
                if (! $builder instanceof EloquentBuilder) {
                    $notEloquentBuilder = $builder::class;
                    throw new DefinitionException("Can not order by relations on non-Eloquent builders, got: {$notEloquentBuilder}.");
                }

                $relation = array_key_first($orderByClause);
                $relationSnake = Str::snake($relation);

                $relationValues = Arr::first($orderByClause);

                $aggregate = $relationValues['aggregate'];
                if ($aggregate === 'count') {
                    $builder->withCount($relation);

                    $column = "{$relationSnake}_count";
                } else {
                    $upperAggregate = ucfirst($aggregate);
                    $operator = "with{$upperAggregate}";
                    $relationColumn = $relationValues['column'];
                    $builder->{$operator}($relation, $relationColumn);

                    $column = "{$relationSnake}_{$aggregate}_{$relationColumn}";
                }
            }

            $builder->orderBy($column, $order);
        }

        return $builder;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->validateMutuallyExclusiveArguments(['columns', 'columnsEnum']);

        if (! $this->hasAllowedColumns() && ! $this->directiveHasArgument('relations')) {
            $argDefinition->type = Parser::typeReference('[' . OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE . '!]');

            return;
        }

        $qualifiedOrderByPrefix = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType);

        $allowedColumnsTypeName = $this->hasAllowedColumns()
            ? $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType)
            : 'String';

        if ($this->directiveHasArgument('relations')) {
            /** @var array<string, string> $relationsInputs */
            $relationsInputs = [];

            foreach ($this->directiveArgValue('relations') as $relation) {
                $relationName = $relation['relation'];
                $relationUpper = ucfirst($relationName);

                $inputName = $qualifiedOrderByPrefix . $relationUpper;

                $relationsInputs[$relationName] = $inputName;

                $columns = $relation['columns'] ?? null;
                if ($columns !== null) {
                    $allowedRelationColumnsEnumName = "{$qualifiedOrderByPrefix}{$relationUpper}Column";

                    $documentAST->setTypeDefinition(
                        $this->createAllowedColumnsEnum(
                            $argDefinition,
                            $parentField,
                            $parentType,
                            $columns,
                            $allowedRelationColumnsEnumName,
                        ),
                    );

                    $documentAST->setTypeDefinition(
                        OrderByServiceProvider::createRelationAggregateFunctionForColumnInput(
                            $inputName,
                            "Aggregate specification for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}.{$relationName}.",
                            $allowedRelationColumnsEnumName,
                        ),
                    );
                } else {
                    $documentAST->setTypeDefinition(
                        OrderByServiceProvider::createRelationAggregateFunctionInput(
                            $inputName,
                            "Aggregate specification for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}.{$relationName}.",
                        ),
                    );
                }
            }

            $qualifiedRelationOrderByName = "{$qualifiedOrderByPrefix}RelationOrderByClause";

            /** @var array<int, string> $relationNames */
            $relationNames = array_keys($relationsInputs);

            $inputMerged = <<<GRAPHQL
                "Order by clause for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}."
                input {$qualifiedRelationOrderByName} {
                    "The column that is used for ordering."
                    column: {$allowedColumnsTypeName} {$this->mutuallyExclusiveRule($relationNames)}

                    "The direction that is used for ordering."
                    order: SortOrder!
GRAPHQL;

            foreach ($relationsInputs as $relation => $input) {
                /** @var array<int, string> $otherOptions */
                $otherOptions = ['column'];
                foreach ($relationNames as $relationName) {
                    if ($relationName !== $relation) {
                        $otherOptions[] = $relationName;
                    }
                }

                $inputMerged .= <<<GRAPHQL
                    "Aggregate specification."
                    {$relation}: {$input} {$this->mutuallyExclusiveRule($otherOptions)}

GRAPHQL;
            }

            $argDefinition->type = Parser::typeReference("[{$qualifiedRelationOrderByName}!]");

            $documentAST->setTypeDefinition(Parser::inputObjectTypeDefinition("{$inputMerged}}"));
        } else {
            $restrictedOrderByName = "{$qualifiedOrderByPrefix}OrderByClause";
            $argDefinition->type = Parser::typeReference("[{$restrictedOrderByName}!]");

            $documentAST->setTypeDefinition(
                OrderByServiceProvider::createOrderByClauseInput(
                    $restrictedOrderByName,
                    "Order by clause for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}.",
                    $allowedColumnsTypeName,
                ),
            );
        }
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        return $builder->orderBy(
            $this->directiveArgValue('column'),
            $this->directiveArgValue('direction', 'ASC'),
        );
    }

    /** @param  array<string>  $otherOptions */
    protected function mutuallyExclusiveRule(array $otherOptions): string
    {
        $optionsString = implode(',', $otherOptions);

        return "@rules(apply: [\"prohibits:{$optionsString}\"])";
    }
}
