<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface InputManipulator extends Directive
{
    /** Manipulate the AST. */
    public function manipulateInputDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputDefinition,
        InputObjectTypeDefinitionNode &$parentType,
    ): void;
}
