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

    public function __construct()
    {
        // Use app() here because Illuminate\Container\Container::environment() is not defined
        $this->env = app()->environment();
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Excludes the annotated element from the schema conditionally.
"""
directive @hide(
  """
  Specify which environments must not use this field, e.g. ["production"].
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /** Should the annotated element be excluded from the schema? */
    protected function shouldHide(): bool
    {
        return in_array($this->env, $this->directiveArgValue('env'));
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if ($this->shouldHide()) {
            $keyToRemove = null;
            foreach ($parentType->fields as $key => $value) {
                if ($value === $fieldDefinition) {
                    $keyToRemove = $key;
                    break;
                }
            }

            unset($parentType->fields[$keyToRemove]);
        }
    }
}
