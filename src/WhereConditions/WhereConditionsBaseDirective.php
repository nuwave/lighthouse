<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

abstract class WhereConditionsBaseDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator
{
    use GeneratesColumnsEnum;

    /**
     * @var \Nuwave\Lighthouse\WhereConditions\Operator
     */
    protected $operator;

    public function __construct(Operator $operator)
    {
        $this->operator = $operator;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  array<string, mixed>  $whereConditions
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleWhereConditions(
        object $builder,
        array $whereConditions,
        Model $model = null,
        string $boolean = 'and'
    ) {
        if ($builder instanceof EloquentBuilder) {
            $model = $builder->getModel();
        }

        if ($andConnectedConditions = $whereConditions['AND'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($andConnectedConditions, $model): void {
                    foreach ($andConnectedConditions as $condition) {
                        $this->handleWhereConditions($builder, $condition, $model);
                    }
                },
                $boolean
            );
        }

        if ($orConnectedConditions = $whereConditions['OR'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($orConnectedConditions, $model): void {
                    foreach ($orConnectedConditions as $condition) {
                        $this->handleWhereConditions($builder, $condition, $model, 'or');
                    }
                },
                $boolean
            );
        }

        if (($hasRelationConditions = $whereConditions['HAS'] ?? null) && $model) {
            $this->handleHasCondition(
                $builder,
                $model,
                $hasRelationConditions['relation'],
                $hasRelationConditions['condition'] ?? null,
                $hasRelationConditions['amount'] ?? null,
                $hasRelationConditions['operator'] ?? null
            );
        }

        if ($column = $whereConditions['column'] ?? null) {
            static::assertValidColumnReference($column);

            return $this->operator->applyConditions($builder, $whereConditions, $boolean);
        }

        return $builder;
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param array<string, mixed>|null $condition
     */
    public function handleHasCondition(
        object $builder,
        Model $model,
        string $relation,
        ?array $condition = null,
        ?int $amount = null,
        ?string $operator = null
    ): void {
        $additionalArguments = [];

        if ($operator !== null) {
            $additionalArguments[] = $operator;
        }

        if ($amount !== null) {
            $additionalArguments[] = $amount;
        }

        $builder->addNestedWhereQuery(
            // @phpstan-ignore-next-line Larastan disagrees with itself here
            $model
                ->whereHas(
                    $relation,
                    function ($builder) use ($relation, $model, $condition): void {
                        if ($condition) {
                            $relatedModel = $this->nestedRelatedModel($model, $relation);

                            $this->handleWhereConditions(
                                $builder,
                                $this->prefixConditionWithTableName(
                                    $condition,
                                    $relatedModel
                                ),
                                $relatedModel
                            );
                        }
                    },
                    ...$additionalArguments
                )
                ->getQuery()
        );
    }

    public static function invalidColumnName(string $column): string
    {
        return "Column names may contain only alphanumerics or underscores, and may not begin with a digit, got: $column";
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        if ($this->hasAllowedColumns()) {
            $restrictedWhereConditionsName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType).$this->generatedInputSuffix();
            $argDefinition->type = Parser::namedType($restrictedWhereConditionsName);
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);

            $documentAST
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createWhereConditionsInputType(
                        $restrictedWhereConditionsName,
                        "Dynamic WHERE conditions for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                )
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createHasConditionsInputType(
                        $restrictedWhereConditionsName,
                        "Dynamic HAS conditions for WHERE conditions for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`."
                    )
                );
        } else {
            $argDefinition->type = Parser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /**
     * Ensure the column name is well formed to prevent SQL injection.
     *
     * @throws \GraphQL\Error\Error
     */
    protected static function assertValidColumnReference(string $column): void
    {
        // A valid column reference:
        // - must not start with a digit, dot or hyphen
        // - must contain only alphanumerics, digits, underscores, dots or hyphens
        // Dots are allowed to reference a column in a table: my_table.my_column.
        $match = \Safe\preg_match('/^(?![0-9.-])[A-Za-z0-9_.-]*$/', $column);
        if ($match === 0) {
            throw new Error(
                self::invalidColumnName($column)
            );
        }
    }

    protected function nestedRelatedModel(Model $model, string $nestedRelationPath): Model
    {
        $relations = explode('.', $nestedRelationPath);
        $relatedModel = $model->newInstance();

        foreach ($relations as $relation) {
            $relatedModel = $relatedModel->{$relation}()->getRelated();
        }

        return $relatedModel;
    }

    /**
     * If the condition references a column, prefix it with the table name.
     *
     * This is important for queries which can otherwise be ambiguous, for
     * example when multiple tables with a column "id" are involved.
     *
     * @param  array<string, mixed>  $condition
     * @return array<string, mixed>
     */
    protected function prefixConditionWithTableName(array $condition, Model $model): array
    {
        if ($column = $condition['column'] ?? null) {
            $condition['column'] = $model->getTable().'.'.$column;
        }

        return $condition;
    }

    /**
     * Get the suffix that will be added to generated input types.
     */
    abstract protected function generatedInputSuffix(): string;
}
