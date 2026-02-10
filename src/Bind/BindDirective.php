<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Container\Container;
use Nuwave\Lighthouse\Bind\Validation\BindingExists;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgumentValidation;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;

class BindDirective extends BaseDirective implements ArgumentValidation, ArgTransformerDirective, ArgDirectiveForArray, ArgManipulator, InputFieldManipulator
{
    /** @var \Nuwave\Lighthouse\Bind\BindDefinition<object>|null */
    protected ?BindDefinition $definition = null;

    protected mixed $binding;

    public function __construct(
        protected Container $container,
    ) {
        $this->binding = new PendingBinding();
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Replace argument values with the corresponding model (or some other value) before passing them to the resolver.
For example, instead of injecting a user's ID, you can inject the entire User model instance that matches the given ID.
This eliminates the need to manually query for the instance inside the resolver.

This works analogues to [Laravel's Route Model Binding](https://laravel.com/docs/routing#route-model-binding).
"""
directive @bind(
    """
    Specify the fully qualified class name of the binding to use.
    This can be either an Eloquent model, or a class that defines a method `__invoke` that resolves the value.
    """
    class: String!

    """
    Specify the column name of a unique identifier to use when binding Eloquent models.
    By default, "id" is used as the primary key column.
    """
    column: String! = "id"

    """
    Specify the relations to eager-load when binding Eloquent models.
    """
    with: [String!]! = []

    """
    Specify whether the binding should be considered required.
    When set to `true`, a validation error will be thrown if the value (or any of the list values) can not be resolved.
    The field resolver will not be invoked in this case.
    When set to `false`, argument values that can not be resolved will be passed to the resolver as `null`.
    When the argument is a list, individual values that can not be resolved will be filtered out.
    """
    required: Boolean! = true
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /** @return \Nuwave\Lighthouse\Bind\BindDefinition<object> */
    protected function bindDefinition(): BindDefinition
    {
        return $this->definition ??= new BindDefinition(
            class: $this->directiveArgValue('class'),
            column: $this->directiveArgValue('column', 'id'),
            with: $this->directiveArgValue('with', []),
            required: $this->directiveArgValue('required', true),
        );
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->bindDefinition()->validate($argDefinition, $parentField);
    }

    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $this->bindDefinition()->validate($inputField, $parentInput);
    }

    public function rules(): array
    {
        return $this->bindDefinition()->required
            ? [new BindingExists($this)]
            : [];
    }

    public function messages(): array
    {
        return [];
    }

    public function attribute(): ?string
    {
        return null;
    }

    public function transform(mixed $argumentValue): mixed
    {
        // When validating required bindings, the \Nuwave\Lighthouse\Bind\Validation\BindingExists validation rule
        // should call transform() before it is called by the directive resolver. To avoid resolving the bindings
        // multiple times, we should remember the resolved binding and reuse it every time transform() is called.
        if (! $this->binding instanceof PendingBinding) {
            return $this->binding;
        }

        $definition = $this->bindDefinition();

        $bind = $definition->isModelBinding()
            ? $this->container->make(ModelBinding::class)
            : $this->container->make($definition->class);

        return $this->binding = $bind($argumentValue, $definition);
    }
}
