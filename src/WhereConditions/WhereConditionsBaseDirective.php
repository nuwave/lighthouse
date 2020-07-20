<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
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
    public function handleWhereConditions(object $builder, array $whereConditions, string $boolean = 'and'): object
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
                );
        } else {
            $argDefinition->type = Parser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /**
     * Ensure the column name is well formed.
     *
     * This prevents SQL injection.
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

    /**
     * Get the suffix that will be added to generated input types.
     */
    abstract protected function generatedInputSuffix(): string;
}
