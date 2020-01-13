<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\Codegen;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class WhereHasConditionsDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator, DefinedDirective
{
    /**
     * @var \Nuwave\Lighthouse\WhereConditions\Operator
     */
    protected $operator;

    /**
     * WhereHasConditionsDirective constructor.
     *
     * @param  \Nuwave\Lighthouse\WhereConditions\Operator  $operator
     * @return void
     */
    public function __construct(Operator $operator)
    {
        $this->operator = $operator;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Allows clients to filter a query based on the existence of a related model, using
a dynamically controlled `WHERE` condition that applies to the relationship.
"""
directive @whereHasConditions(
    """
    The Eloquent relationship that the conditions will be applied to.
    This argument can be ommited if the field name and the relationship name are the same.
    """
    relation: String

    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    """
    columns: [String!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed[]  $whereConditions
     * @param  bool  $init
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, array $whereConditions, bool $init = true, string $boolean = 'and')
    {
        if ($init) {
            // Make sure to ignore empty conditions.
            // The "operator" key set by default, so the count is 1 when the condition is empty.
            if (count($whereConditions) > 1) {
                $relationName = $this->directiveArgValue('relation')
                    // Use the name of the argument if no explicit relation name is given.
                    ?? $this->nodeName();

                $builder->whereHas(
                    $relationName,
                    function ($builder) use ($whereConditions): void {
                        // This extra nesting is required for the `OR` condition to work correctly.
                        $builder->whereNested(
                            function ($builder) use ($whereConditions): void {
                                $this->handleBuilder($builder, $whereConditions, false);
                            }
                        );
                    }
                );
            }
        } else {
            if ($andConnectedConditions = $whereConditions['AND'] ?? null) {
                $builder->whereNested(
                    function ($builder) use ($andConnectedConditions): void {
                        foreach ($andConnectedConditions as $condition) {
                            $this->handleBuilder($builder, $condition, false);
                        }
                    }
                );
            }

            if ($orConnectedConditions = $whereConditions['OR'] ?? null) {
                $builder->whereNested(
                    function ($builder) use ($orConnectedConditions): void {
                        foreach ($orConnectedConditions as $condition) {
                            $this->handleBuilder($builder, $condition, false, 'or');
                        }
                    },
                    'or'
                );
            }

            if ($column = $whereConditions['column'] ?? null) {
                static::assertValidColumnName($column);

                return $this->operator->applyConditions($builder, $whereConditions, $boolean);
            }
        }

        return $builder;
    }

    public static function invalidColumnName(string $column): string
    {
        return "Column names may contain only alphanumerics or underscores, and may not begin with a digit, got: $column";
    }

    /**
     * Manipulate the AST.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $argDefinition
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $parentField
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        if ($allowedColumns = $this->directiveArgValue('columns')) {
            $restrictedWhereHasConditionsName = $this->restrictedWhereHasConditionsName($argDefinition, $parentField);
            $argDefinition->type = PartialParser::namedType($restrictedWhereHasConditionsName);

            $allowedColumnsEnumName = Codegen::allowedColumnsEnumName($argDefinition, $parentField);

            $documentAST
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createWhereConditionsInputType(
                        $restrictedWhereHasConditionsName,
                        "Dynamic relationship WHERE conditions for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                )
                ->setTypeDefinition(
                    Codegen::createAllowedColumnsEnum($argDefinition, $parentField, $allowedColumns, $allowedColumnsEnumName)
                );
        } else {
            $argDefinition->type = PartialParser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /**
     * Create the name for the restricted WhereHasConditions input.
     *
     * @example FieldNameArgNameWhereHasConditions
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $argDefinition
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $parentField
     * @return string
     */
    protected function restrictedWhereHasConditionsName(InputValueDefinitionNode &$argDefinition, FieldDefinitionNode &$parentField): string
    {
        return Str::studly($parentField->name->value)
            .Str::studly($argDefinition->name->value)
            .'WhereConditions';
    }

    /**
     * Ensure the column name is well formed and prevent SQL injection.
     *
     * @param  string  $column
     * @return void
     *
     * @throws \GraphQL\Error\Error
     */
    protected static function assertValidColumnName(string $column): void
    {
        if (! \Safe\preg_match('/^(?![0-9])[A-Za-z0-9_-]*$/', $column)) {
            throw new Error(
                self::invalidColumnName($column)
            );
        }
    }
}
