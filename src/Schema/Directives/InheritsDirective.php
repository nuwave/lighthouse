<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class InheritsDirective extends BaseDirective implements TypeManipulator
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
This allows types to inherits fields from another type.
"""
directive @inherits(
    """
    The type from where it will inherit.
    """
    from: String!
) on OBJECT
GRAPHQL;
    }

    /**
     * @param   \Nuwave\Lighthouse\Schema\AST\DocumentAST         $documentAST
     * @param   \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     *
     * @return  void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition)
    {
        $parentType = $documentAST->types[$this->directiveArgValue('from')];

        if ($typeDefinition->kind !== $parentType->kind) {
            throw new DefinitionException(
                "The {$typeDefinition->name->value} ({$typeDefinition->kind} found) kind must be the same as {$parentType->name->value} ({$parentType->kind} found)."
            );
        }

        $typeDefinition->fields = $parentType->fields->merge($typeDefinition->fields);
    }
}
