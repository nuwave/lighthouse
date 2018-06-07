<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;

interface NodeManipulator extends Directive
{
    /**
     * @param ObjectTypeDefinitionNode $definitionNode
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(ObjectTypeDefinitionNode $definitionNode, DocumentAST $current, DocumentAST $original);
}
