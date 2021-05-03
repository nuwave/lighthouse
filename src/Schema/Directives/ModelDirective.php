<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class ModelDirective extends BaseDirective
{
    const NAME = 'model';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Map a model class to an object type.

This can be used when the name of the model differs from the name of the type.
"""
directive @model(
  """
  The class name of the corresponding model.
  """
  class: String!
) on OBJECT
GRAPHQL;
    }

    /**
     * Attempt to get the model class name from this directive.
     */
    public static function modelClass(ObjectTypeDefinitionNode $node): ?string
    {
        $modelDirective = ASTHelper::directiveDefinition($node, self::NAME);
        if ($modelDirective !== null) {
            return ASTHelper::directiveArgValue($modelDirective, 'class');
        }

        return null;
    }
}
