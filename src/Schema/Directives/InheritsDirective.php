<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\NodeList;
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
    The parent Type
    """
    parent: String!
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
        $parent_name = $this->getMethodArgumentParts('parent')[0];

        $parent = $documentAST->types[$parent_name];

        if ($typeDefinition->kind !== $parent->kind) {
            throw new DefinitionException(
                "The {$typeDefinition->name->value} ({$typeDefinition->kind} Found!) kind must the same as {$parent->name->value} ({$parent->kind} Found!)."
            );
        }

        foreach (get_object_vars($parent) as $type => $value) {
            if ($type == 'name') {
                continue;
            }
            if ($value instanceof NodeList) {
                $typeDefinition->{$type} = $parent->{$type}->merge($typeDefinition->{$type});
            } else {
                $typeDefinition->{$type} = $typeDefinition->{$type} ?? $parent->{$type};
            }
        }

        $documentAST->setTypeDefinition($typeDefinition);
    }
}
