<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface NodeManipulator extends Directive
{
    /**
     * @param Node        $node
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original);
}
