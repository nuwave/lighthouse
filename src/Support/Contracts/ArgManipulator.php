<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface ArgManipulator extends Directive
{
    /**
     * @param InputValueDefinitionNode $argDefinition
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(InputValueDefinitionNode $argDefinition, FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original);
}
