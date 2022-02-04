<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\TypeExtensionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface TypeExtensionManipulator extends Directive
{
    /**
     * Apply manipulations from a type extension node.
     *
     * @return void
     */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension);
}
