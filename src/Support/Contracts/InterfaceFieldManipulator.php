<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface InterfaceFieldManipulator extends Directive
{
    /**
     * Manipulate the AST based on a field definition.
     *
     * @return void
     */
    public function manipulateInterfaceFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        InterfaceTypeDefinitionNode &$parentType
    );
}
