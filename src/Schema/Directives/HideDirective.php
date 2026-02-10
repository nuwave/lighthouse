<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class HideDirective extends BaseDirective implements ArgManipulator, FieldManipulator, InputFieldManipulator, TypeManipulator
{
    protected string $env;

    public function __construct()
    {
        $app = Container::getInstance();
        assert($app instanceof Application);

        $environment = $app->environment();
        assert(is_string($environment), 'Calling this method without parameters returns the current value of the environment.'); // @phpstan-ignore function.alreadyNarrowedType,function.alreadyNarrowedType (dynamic type known only with the latest Larastan version)

        $this->env = $environment;
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
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION | OBJECT
GRAPHQL;
    }

    /** Should the annotated element be excluded from the schema? */
    protected function shouldHide(): bool
    {
        return in_array($this->env, $this->directiveArgValue('env'));
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (! $this->shouldHide()) {
            return;
        }

        foreach ($parentType->fields as $key => $value) {
            if ($value === $fieldDefinition) {
                unset($parentType->fields[$key]);
                break;
            }
        }
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if (! $this->shouldHide()) {
            return;
        }

        foreach ($documentAST->types as $key => $value) {
            if ($value === $typeDefinition) {
                unset($documentAST->types[$key]);
                break;
            }
        }
    }

    public function manipulateInputFieldDefinition(DocumentAST &$documentAST, InputValueDefinitionNode &$inputField, InputObjectTypeDefinitionNode &$parentInput): void
    {
        if (! $this->shouldHide()) {
            return;
        }

        foreach ($parentInput->fields as $key => $value) {
            if ($value === $inputField) {
                unset($parentInput->fields[$key]);
                break;
            }
        }
    }

    public function manipulateArgDefinition(DocumentAST &$documentAST, InputValueDefinitionNode &$argDefinition, FieldDefinitionNode &$parentField, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (! $this->shouldHide()) {
            return;
        }

        foreach ($parentField->arguments as $key => $value) {
            if ($value === $argDefinition) {
                unset($parentField->arguments[$key]);
                break;
            }
        }
    }
}
