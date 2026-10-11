<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

interface InputFieldManipulator extends Directive
{
    /** Manipulate the AST. */
    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void;
}
