<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

interface FieldManipulator extends Directive
{
    /**
     * Manipulate the AST.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $current
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(
        FieldDefinitionNode $fieldDefinition,
        ObjectTypeDefinitionNode $parentType,
        DocumentAST $current
    );
}
