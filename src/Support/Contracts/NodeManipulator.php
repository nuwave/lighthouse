<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface NodeManipulator extends Directive
{
    /**
     * @param Node        $node
     * @param DocumentAST $documentAST
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST);
}
