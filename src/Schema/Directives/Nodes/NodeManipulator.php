<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface NodeManipulator extends Directive
{
    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(ObjectTypeDefinitionNode $objectType, DocumentAST $current, DocumentAST $original);
}
