<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface TypeManipulator extends Directive
{
    /**
     * Apply manipulations from a type definition node.
     *
     * @return void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition);
}
