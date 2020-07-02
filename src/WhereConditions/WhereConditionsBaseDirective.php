<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

abstract class WhereConditionsBaseDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator, DefinedDirective
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
    public function handleWhereConditions($builder, array $whereConditions, string $boolean = 'and')
    {
        if ($andConnectedConditions = $whereConditions['AND'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($andConnectedConditions): void {
                    foreach ($andConnectedConditions as $condition) {
                        $this->handleWhereConditions($builder, $condition);
                    }
                },
                $boolean
            );
        }

        if ($orConnectedConditions = $whereConditions['OR'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($orConnectedConditions): void {
                    foreach ($orConnectedConditions as $condition) {
                        $this->handleWhereConditions($builder, $condition, 'or');
                    }
                },
                $boolean
            );
        }

        if ($column = $whereConditions['column'] ?? null) {
            static::assertValidColumnName($column);

            return $this->operator->applyConditions($builder, $whereConditions, $boolean);
        }

        return $builder;
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
            $restrictedWhereConditionsName = $this->restrictedWhereConditionsName($argDefinition, $parentField);
            $argDefinition->type = PartialParser::namedType($restrictedWhereConditionsName);
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField);

            $documentAST
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createWhereConditionsInputType(
                        $restrictedWhereConditionsName,
                        "Dynamic WHERE conditions for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                );
        } else {
            $argDefinition->type = PartialParser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /**
     * Create the name for the restricted WhereConditions input.
     *
     * @example FieldNameArgNameWhereHasConditions
     */
    protected function restrictedWhereConditionsName(InputValueDefinitionNode &$argDefinition, FieldDefinitionNode &$parentField): string
    {
        return Str::studly($parentField->name->value)
            .Str::studly($argDefinition->name->value)
            .'WhereConditions';
    }

    /**
     * Ensure the column name is well formed and prevent SQL injection.
     *
     * @throws \GraphQL\Error\Error
     */
    protected static function assertValidColumnName(string $column): void
    {
        // TODO use safe
        $match = preg_match('/^(?![0-9])[A-Za-z0-9_-]*$/', $column);
        if ($match === 0) {
            throw new Error(
                self::invalidColumnName($column)
            );
        }
    }
}
