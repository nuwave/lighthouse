<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface InputValueManipulator extends Directive
{
    /**
     * @param InputValueDefinitionNode      $inputValue
     * @param InputObjectTypeDefinitionNode $parentType
     * @param DocumentAST                   $current
     * @param DocumentAST                   $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(
        InputValueDefinitionNode $inputValue,
        InputObjectTypeDefinitionNode $parentType,
        DocumentAST $current,
        DocumentAST $original
    );
}
