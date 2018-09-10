<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

interface FieldManipulator extends Directive
{
    /**
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     *
     * @return DocumentAST
     */
    public function manipulateSchema(
        FieldDefinitionNode $fieldDefinition,
        ObjectTypeDefinitionNode $parentType,
        DocumentAST $current
    );
}
