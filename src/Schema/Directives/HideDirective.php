<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class HideDirective extends BaseDirective implements FieldManipulator
{
    protected string $env;

    public function __construct(
    ) {
        $this->env = app()->environment();
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Hides field if environment is one of specified
"""
directive @hide(
  """
  Specify which environments must not use this field, e.g. ["prod"].
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /** Checks if the current field must be hidden. Can be overridden. */
    protected function shouldHide(): bool
    {
        $envs = $this->directiveArgValue('env');

        return in_array($this->env, $envs);
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (! $this->shouldHide()) {
            return;
        }

        $index = collect($parentType->fields)->search($fieldDefinition);
        assert($index !== false);
        $parentType->fields->splice($index, 1);
    }
}
