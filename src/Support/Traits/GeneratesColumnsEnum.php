<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Directives may want to constrain database columns to an enum.
 *
 * @mixin \Nuwave\Lighthouse\Schema\Directives\BaseDirective
 */
trait GeneratesColumnsEnum
{
    /**
     * Check whether the directive constrains allowed columns.
     */
    protected function hasAllowedColumns(): bool
    {
        $hasColumns = ! is_null($this->directiveArgValue('columns'));
        $hasColumnsEnum = ! is_null($this->directiveArgValue('columnsEnum'));

        if ($hasColumns && $hasColumnsEnum) {
            throw new DefinitionException(
                "The @{$this->name()} directive can only have one of the following arguments: `columns`, `columnsEnum`."
            );
        }

        return $hasColumns || $hasColumnsEnum;
    }

    /**
     * Generate the enumeration type for the list of allowed columns.
     *
     * @return string the name of the used enum
     */
    protected function generateColumnsEnum(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): string {
        $columnsEnum = $this->directiveArgValue('columnsEnum');
        if (! is_null($columnsEnum)) {
            return $columnsEnum;
        }

        $allowedColumnsEnumName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . 'Column';

        $documentAST
            ->setTypeDefinition(
                static::createAllowedColumnsEnum(
                    $argDefinition,
                    $parentField,
                    $parentType,
                    $this->directiveArgValue('columns'),
                    $allowedColumnsEnumName
                )
            );

        return $allowedColumnsEnumName;
    }

    /**
     * Create the Enum that holds the allowed columns.
     *
     * @param  array<mixed, string>  $allowedColumns
     */
    protected function createAllowedColumnsEnum(
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType,
        array $allowedColumns,
        string $allowedColumnsEnumName
    ): EnumTypeDefinitionNode {
        $enumValues = array_map(
            function (string $columnName): string {
                return
                    strtoupper(
                        Str::snake($columnName)
                    )
                    . ' @enum(value: "' . $columnName . '")';
            },
            $allowedColumns
        );

        $enumValuesString = implode("\n", $enumValues);

        return Parser::enumTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Allowed column names for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}."
enum $allowedColumnsEnumName {
    {$enumValuesString}
}
GRAPHQL
        );
    }
}
