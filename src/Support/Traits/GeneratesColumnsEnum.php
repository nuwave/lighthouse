<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\Codegen;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

trait GeneratesColumnsEnum
{
    /**
     * Check whether the directive has a list of allowed columns.
     *
     * @return bool
     */
    public function hasAllowedColumns(): bool
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
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\InputValueDefinitionNode $argDefinition
     * @param \GraphQL\Language\AST\FieldDefinitionNode $parentField
     * @return string
     */
    public function generateColumnsEnum(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField
    ): string {
        $columnsEnum = $this->directiveArgValue('columnsEnum');

        if (! is_null($columnsEnum)) {
            return $columnsEnum;
        }

        $allowedColumnsEnumName = Codegen::allowedColumnsEnumName($argDefinition, $parentField);

        $documentAST
            ->setTypeDefinition(
                Codegen::createAllowedColumnsEnum(
                    $argDefinition,
                    $parentField,
                    $this->directiveArgValue('columns'),
                    $allowedColumnsEnumName
                )
            );

        return $allowedColumnsEnumName;
    }
}
