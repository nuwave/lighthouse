<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

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
                'The @'.$this->name().' directive can only have one of the following arguments: `columns`, `columnsEnum`.'
            );
        }

        return $hasColumns || $hasColumnsEnum;
    }

    /**
     * Generate the enumeration type for the list of allowed columns.
     *
     * @return string The name of the used enum.
     */
    protected function generateColumnsEnum(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField
    ): string {
        $columnsEnum = $this->directiveArgValue('columnsEnum');

        if (! is_null($columnsEnum)) {
            return $columnsEnum;
        }

        $allowedColumnsEnumName = static::allowedColumnsEnumName($argDefinition, $parentField);

        $documentAST
            ->setTypeDefinition(
                static::createAllowedColumnsEnum(
                    $argDefinition,
                    $parentField,
                    $this->directiveArgValue('columns'),
                    $allowedColumnsEnumName
                )
            );

        return $allowedColumnsEnumName;
    }

    /**
     * Create the name for the Enum that holds the allowed columns.
     *
     * @example FieldNameArgNameColumn
     */
    protected function allowedColumnsEnumName(
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField
    ): string {
        return Str::studly($parentField->name->value)
            .Str::studly($argDefinition->name->value)
            .'Column';
    }

    /**
     * Create the Enum that holds the allowed columns.
     *
     * @param  string[]  $allowedColumns
     */
    protected function createAllowedColumnsEnum(
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        array $allowedColumns,
        string $allowedColumnsEnumName
    ): EnumTypeDefinitionNode {
        $enumValues = array_map(
            function (string $columnName): string {
                return
                    strtoupper(
                        Str::snake($columnName)
                    )
                    .' @enum(value: "'.$columnName.'")';
            },
            $allowedColumns
        );

        $enumDefinition = "\"Allowed column names for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.\"\n"
            ."enum $allowedColumnsEnumName {\n";
        foreach ($enumValues as $enumValue) {
            $enumDefinition .= "$enumValue\n";
        }
        $enumDefinition .= '}';

        return PartialParser::enumTypeDefinition($enumDefinition);
    }
}
