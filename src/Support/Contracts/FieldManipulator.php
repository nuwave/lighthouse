<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface FieldManipulator extends Directive
{
    /**
     * Manipulate the AST based on a field definition.
     *
     * @param  ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode  $parentType
     * @return void
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        &$parentType
    );
}
