<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\TypeDefinitionNode;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class InheritsDirective extends BaseDirective implements TypeManipulator
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
All fields & properties of the parent type will be inherited by the child type.
"""
directive @inherits(
    """
    Specify the parent type to inherit from.
    """
    from: String!
) on OBJECT
GRAPHQL;
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition)
    {
        $parentType = Arr::get($documentAST->types, $this->directiveArgValue('from'));

        if (! $parentType) {
            throw new DefinitionException("The type {$this->directiveArgValue('from')} was not found in your schama.");
        }

        if ($typeDefinition->kind !== $parentType->kind) {
            throw new DefinitionException(
                "The {$typeDefinition->name->value} ({$typeDefinition->kind} found) kind must be the same as {$parentType->name->value} ({$parentType->kind} found)."
            );
        }
        

        if ($this->checkIfParentHasInheritedFromChild($parentType, $typeDefinition)) {
            throw new DefinitionException(
                "{$typeDefinition->name->value} can't inherit from {$parentType->name->value}"
            );
        }

        foreach (get_object_vars($parentType) as $type => $value) {
            if ($value instanceof NodeList) {
                $typeDefinition->{$type} = ASTHelper::mergeUniqueNodeList($parentType->{$type}, $typeDefinition->{$type}, true);
            } else {
                $typeDefinition->{$type} = $typeDefinition->{$type} ?? $parentType->{$type};
            }
        }

        $documentAST->setTypeDefinition($typeDefinition);
    }

    protected function checkIfParentHasInheritedFromChild($parentType, $typeDefinition)
    {
        $parentTypeDirective = ASTHelper::directiveDefinition($parentType, 'inherits');
        $chilTypeDirective = ASTHelper::directiveDefinition($typeDefinition, 'inherits');

        if (
          $parentTypeDirective && $chilTypeDirective
        ) {
            return ASTHelper::directiveArgValue($parentTypeDirective, 'from') == $typeDefinition->name->value
            && ASTHelper::directiveArgValue($chilTypeDirective, 'from') == $parentType->name->value;
        }

        return false;
    }
}
