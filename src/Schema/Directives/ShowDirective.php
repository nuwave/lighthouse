<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class ShowDirective extends HideDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Includes the annotated element from the schema conditionally.
"""
directive @show(
  """
  Specify which environments may use this field, e.g. ["testing"].
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /** Should the annotated element be excluded from the schema? */
    protected function shouldHide(): bool
    {
        return !parent::shouldHide();
    }
}
