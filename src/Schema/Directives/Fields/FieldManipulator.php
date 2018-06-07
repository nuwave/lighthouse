<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface FieldManipulator extends Directive
{
    /**
     * @param FieldDefinitionNode      $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current, DocumentAST $original);
}
