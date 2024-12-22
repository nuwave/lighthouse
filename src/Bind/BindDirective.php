<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Container\Container;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;

class BindDirective extends BaseDirective implements ArgTransformerDirective, ArgDirectiveForArray, ArgManipulator, InputFieldManipulator
{
    public function __construct(
        private Container $container,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Automatically inject (model) instances directly into a resolver argument or input field. For example, instead 
of injecting a user's ID, you can inject the entire User model instance that matches the given ID.

This is a GraphQL analogue for Laravel's Route Binding.
"""
directive @bind(
    """
    Specify the class name of the binding to use. This can be either an Eloquent 
    model or callable class to bind any other instance than a model.
    """
    class: String!
    
    """
    Specify the column name to use when binding Eloquent models.
    By default, "id" is used the the primary key column.
    """
    column: String! = "id"
    
    """
    Specify the relations to eager-load when binding Eloquent models.
    """
    with: [String!]! = []
    
    """
    Specify whether the binding should be considered optional. When optional, the argument's 
    value is set to `null` when no matching binding could be resolved. When the binding 
    isn't optional, an exception is thrown and the field resolver will not be invoked. 
    """
    optional: Boolean! = false
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->bindingDefinition()->validate([
            'argument' => $argDefinition->name->value,
            'field' => $parentField->name->value,
        ]);
    }

    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $this->bindingDefinition()->validate([
            'field' => $inputField->name->value,
            'input' => $parentInput->name->value,
        ]);
    }

    public function transform(mixed $argumentValue): mixed
    {
        $definition = $this->bindingDefinition();
        $bind = match ($definition->isModelBinding()) {
            true => new ModelBinding(),
            false => $this->container->make($definition->class),
        };

        return $bind($argumentValue, $definition);
    }

    private function bindingDefinition(): BindDefinition
    {
        return new BindDefinition(
            $this->directiveArgValue('class'),
            $this->directiveArgValue('column', 'id'),
            $this->directiveArgValue('with', []),
            $this->directiveArgValue('optional', false),
        );
    }
}
